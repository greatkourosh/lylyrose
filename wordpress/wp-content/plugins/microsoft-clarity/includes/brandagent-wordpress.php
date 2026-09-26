<?php
/**
 * Brand Agent — plain WordPress (non-commerce) connect.
 *
 * Provisions the per-store HMAC secret for a plain WordPress site without the
 * WooCommerce wc-auth grant. Because the plugin runs in wp-admin under a
 * privileged user, the connect handshake is a direct server-to-server call to the
 * Clarity dashboard, which mints the secret, registers the advertiser with the
 * BrandAgent backend (Platform=WordPress) and returns the secret to the plugin.
 *
 * Store ownership is proven with a one-time nonce loopback: connect stores a nonce
 * here (admin-privileged) and sends it to the dashboard, which calls back to
 * connect-verify below before minting. A forged connect naming another site cannot
 * pass because only that site's plugin holds the matching nonce.
 *
 * @package MicrosoftClarity
 */

defined( 'ABSPATH' ) || exit;

/**
 * Run the plain-WordPress connect handshake.
 *
 * @param array|null $request Optional AJAX request whose project and retry state must be prepared
 *                            under the same lock as Connect.
 * @return array Result with at least a boolean 'success' key.
 */
function brandagent_wordpress_connect( $request = null ) {
	$connect_lock = brandagent_wordpress_acquire_connect_lock();
	if ( false === $connect_lock ) {
		return array(
			'success'    => false,
			'error'      => 'WordPress connect is already in progress.',
			'error_code' => 'connect_in_progress',
		);
	}

	try {
		return brandagent_wordpress_connect_locked( $request );
	} finally {
		brandagent_wordpress_release_connect_lock( $connect_lock );
	}
}

/**
 * Acquire the per-site connect lock, recovering it after a crashed/timed-out request.
 *
 * WordPress add_option() uses ON DUPLICATE KEY UPDATE, so it cannot provide insert-if-absent lock
 * semantics. Use an INSERT IGNORE against the option-name unique key instead: two successful
 * connects mint different random secrets, and allowing them to overlap can make the plugin retain
 * one response after Brand Agent has already stored the other.
 *
 * @return string|false The owned lock value, or false when another request holds it.
 */
function brandagent_wordpress_acquire_connect_lock() {
	$lock_key = 'brandagent_wp_connect_lock';
	$now      = time();
	$owner    = $now . ':' . wp_generate_password( 32, false );

	if ( brandagent_wordpress_try_insert_connect_lock( $lock_key, $owner ) ) {
		return $owner;
	}

	$existing  = (string) get_option( $lock_key, '' );
	$lock_time = (int) strtok( $existing, ':' );
	if ( $lock_time > 0 && ( $now - $lock_time ) < 2 * MINUTE_IN_SECONDS ) {
		return false;
	}

	// Delete only the exact stale value we observed. A plain delete_option() can erase a fresh lock
	// installed by another request between our read and delete, allowing two owners at once.
	global $wpdb;
	$deleted = $wpdb->query( $wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
		$lock_key,
		$existing
	) );
	if ( 1 !== $deleted ) {
		return false;
	}

	brandagent_wordpress_clear_connect_lock_cache( $lock_key );
	return brandagent_wordpress_try_insert_connect_lock( $lock_key, $owner ) ? $owner : false;
}

/**
 * Atomically insert a connect lock without replacing an existing owner.
 *
 * @param string $lock_key Option name used for the lock.
 * @param string $owner    Unique owned lock value.
 * @return bool Whether this request inserted the lock.
 */
function brandagent_wordpress_try_insert_connect_lock( $lock_key, $owner ) {
	global $wpdb;
	$inserted = $wpdb->query( $wpdb->prepare(
		"INSERT IGNORE INTO `{$wpdb->options}` (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, %s)",
		$lock_key,
		$owner,
		'no'
	) );
	if ( 1 !== $inserted ) {
		return false;
	}

	brandagent_wordpress_clear_connect_lock_cache( $lock_key );
	return true;
}

/**
 * Invalidate option caches after changing the lock directly in the database.
 *
 * @param string $lock_key Option name used for the lock.
 * @return void
 */
function brandagent_wordpress_clear_connect_lock_cache( $lock_key ) {
	wp_cache_delete( $lock_key, 'options' );
	wp_cache_delete( 'notoptions', 'options' );
}

/**
 * Release the connect lock only if this request still owns it.
 *
 * @param string $owner Owned lock value returned by brandagent_wordpress_acquire_connect_lock().
 * @return void
 */
function brandagent_wordpress_release_connect_lock( $owner ) {
	global $wpdb;
	$lock_key = 'brandagent_wp_connect_lock';
	$deleted  = $wpdb->query( $wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s",
		$lock_key,
		(string) $owner
	) );
	if ( 1 === $deleted ) {
		brandagent_wordpress_clear_connect_lock_cache( $lock_key );
	}
}

/**
 * Update the Clarity project while excluding an in-flight WordPress Connect.
 *
 * @param mixed $project_id Project value accepted by the existing project-change endpoint.
 * @return array Result with a boolean success key and a machine-readable error code on failure.
 */
function brandagent_wordpress_update_project_id( $project_id ) {
	$connect_lock = brandagent_wordpress_acquire_connect_lock();
	if ( false === $connect_lock ) {
		return array(
			'success'    => false,
			'error'      => 'Brand Agent connection is already in progress.',
			'error_code' => 'connect_in_progress',
		);
	}

	try {
		update_option( 'clarity_project_id', $project_id );
		// update_option() also returns false when the requested value is already stored, so verify
		// the readback instead of treating its return value as a persistence failure.
		if ( $project_id !== get_option( 'clarity_project_id', null ) ) {
			return array(
				'success'    => false,
				'error'      => 'Clarity project ID failed to persist.',
				'error_code' => 'project_id_persist_failed',
			);
		}

		return array( 'success' => true );
	} finally {
		brandagent_wordpress_release_connect_lock( $connect_lock );
	}
}

/**
 * Execute Connect while the caller owns brandagent_wp_connect_lock.
 *
 * @param array|null $request                      Optional AJAX request to prepare before Connect.
 * @param bool       $allow_stale_site_id_recovery Whether one stale-ID recovery retry is allowed.
 * @return array Result with at least a boolean 'success' key.
 */
function brandagent_wordpress_connect_locked( $request = null, $allow_stale_site_id_recovery = true ) {
	// WooCommerce stores must onboard through the wc-auth flow. The plugin is the only component
	// that knows this reliably at request time: the dashboard decides eligibility from the
	// hasWooCommerce flag recorded on the integration, which goes stale when WooCommerce is
	// activated after Clarity. Without this guard a stale-eligibility connect would be interpreted
	// by the backend as an explicit WooCommerce-to-WordPress transition, tearing down commerce
	// artifacts and replacing the shared local credential while WooCommerce is still active.
	// Backstop every entry point, including the admin-only REST route below.
	//
	// Uses the activation state rather than class_exists(): a store whose WooCommerce is active but
	// did not load this request (its own PHP-version guard bailing, a missing plugin file) is still
	// a WooCommerce store, and class_exists() would wave it through. Same helper clarity.php uses to
	// pick the lifecycle endpoint, so the plugin cannot answer "is this a store" two different ways.
	if ( clarity_is_woocommerce_active_for_current_blog() ) {
		brandagent_log( 'BrandAgent WordPress Connect: refused, WooCommerce is active on this site' );
		return array( 'success' => false, 'error' => 'WooCommerce site must use the WooCommerce connect flow.' );
	}

	if ( null !== $request ) {
		$project_result = brandagent_wordpress_prepare_connect_project_id( $request );
		if ( empty( $project_result['success'] ) ) {
			return $project_result;
		}

		// Clicking Continue is the opt-in. Initialize recovery only after project validation and
		// persistence succeed, while the project write and ensuing Connect still share this lock.
		update_option( 'brandagent_wp_connect_optin', 1 );
		delete_option( 'brandagent_wp_connect_attempts' );
		delete_transient( 'brandagent_wp_connect_throttle' );
	}

	// Reconnects rotate credentials for the existing connection; they do not let a request-context
	// home_url() (locale, proxy, or alternate domain) silently rebind its backend identity.
	$store_url          = brandagent_get_connected_store_url();
	$project_id         = get_option( 'clarity_project_id', '' );
	$wp_site_id         = get_option( 'clarity_wordpress_site_id', '' );
	$recover_wp_site_id = '' === $wp_site_id;

	$clarity_server_url = BrandAgent_Config::get_clarity_server_url();
	if ( empty( $clarity_server_url ) ) {
		return array( 'success' => false, 'error' => 'clarity_server_url not configured' );
	}

	$connect_url = trailingslashit( $clarity_server_url ) . 'wordpress/connect';

	// Store-ownership proof: mint a one-time nonce that the dashboard verifies by calling
	// back to connect-verify before it issues the HMAC secret. Persist only the hash, so a
	// read of wp_options/object cache never exposes a usable nonce. Short-lived + one-time.
	$connect_nonce = wp_generate_password( 64, false );
	$nonce_digest  = hash( 'sha256', $connect_nonce );
	$attempt_id    = substr( $nonce_digest, 0, 16 );
	set_transient( brandagent_wordpress_connect_nonce_key( $connect_nonce ), $nonce_digest, 10 * MINUTE_IN_SECONDS );
	if ( $recover_wp_site_id ) {
		// Keep recovery state separate from the nonce digest. Older plugin/backend combinations expect
		// the nonce transient itself to be a string, and changing its shape would break ownership proof
		// during a rolling deployment.
		set_transient(
			brandagent_wordpress_connect_recovery_key( $connect_nonce ),
			array( 'clarityProjectId' => $project_id ),
			10 * MINUTE_IN_SECONDS
		);
	}

	$connect_request = array(
		'storeUrl'         => $store_url,
		'clarityProjectId' => $project_id,
		'wordpressSiteId'  => $wp_site_id,
		'connectNonce'     => $connect_nonce,
	);
	if ( $recover_wp_site_id ) {
		// This opt-in is intentionally absent for every healthy/new install. An older dashboard safely
		// ignores the additive field and continues to reject the empty ID as it does today.
		$connect_request['wordpressSiteIdRecovery'] = true;
	}
	$body = wp_json_encode( $connect_request );

	brandagent_log( 'BrandAgent WordPress Connect: starting', array( 'store_url' => $store_url, 'endpoint' => $connect_url, 'attempt_id' => $attempt_id ) );

	// The dashboard mints the secret and commits it to Key Vault before it answers, so from this
	// point on the credential this site holds may already be stale — including when the reply never
	// arrives (30s timeout below) or comes back non-200 after the backend call succeeded. Mark the
	// connection unconfirmed for the whole round trip and clear it only once a readback proves this
	// side holds the same secret. brandagent_wordpress_maybe_resume_connect() consults this so a
	// stale-but-readable credential cannot make the site look connected and cancel its own retries,
	// which is what turned a recoverable failure into a permanent desync.
	update_option( 'brandagent_wp_connect_unverified', 1 );

	$response = wp_remote_post( $connect_url, array(
		'timeout' => 30,
		'headers' => array( 'Content-Type' => 'application/json' ),
		'body'    => $body,
	) );

	if ( is_wp_error( $response ) ) {
		brandagent_log( 'BrandAgent WordPress Connect: transport error', array( 'error' => $response->get_error_message(), 'attempt_id' => $attempt_id ) );
		return array( 'success' => false, 'error' => $response->get_error_message() );
	}

	$code = wp_remote_retrieve_response_code( $response );
	$data = json_decode( wp_remote_retrieve_body( $response ), true );
	$data = is_array( $data ) ? $data : array();

	if ( $code !== 200 || empty( $data['hmac_secret'] ) ) {
		$error_code = brandagent_wordpress_connect_error_code( $data );
		brandagent_log( 'BrandAgent WordPress Connect: unexpected response', array( 'status' => $code, 'error_code' => $error_code, 'attempt_id' => $attempt_id ) );

		// The legacy in-dashboard updater can reactivate an existing install whose local site ID is
		// empty, generating a new UUID that does not match the integration already stored
		// in Clarity. Only the exact binding failure is eligible for a single retry through the existing
		// empty-ID recovery protocol. Clearing uses compare-and-swap under the connect lock so a
		// concurrent lifecycle write is never overwritten; the backend still requires the project,
		// URL and nonce-loopback ownership checks before it can return and persist the canonical UUID.
		if (
			$allow_stale_site_id_recovery &&
			403 === $code &&
			'wordpressintegrationverificationfailed' === $error_code &&
			is_string( $wp_site_id ) &&
			brandagent_wordpress_is_valid_site_id( $wp_site_id ) &&
			brandagent_wordpress_replace_site_id_if_matches( $wp_site_id, '' )
		) {
			// This rejected attempt never needs to remain challengeable while the retry uses a fresh
			// nonce. Recovery is explicitly disabled on the nested attempt to bound the operation even
			// if another lifecycle request writes a different UUID while the retry is in flight.
			delete_transient( brandagent_wordpress_connect_nonce_key( $connect_nonce ) );
			delete_transient( brandagent_wordpress_connect_recovery_key( $connect_nonce ) );
			brandagent_log( 'BrandAgent WordPress Connect: retrying after stale site ID rejection', array( 'attempt_id' => $attempt_id ) );

			$retry_result = null;
			try {
				$retry_result = brandagent_wordpress_connect_locked( null, false );
			} finally {
				if ( ( ! is_array( $retry_result ) || empty( $retry_result['success'] ) ) && '' === get_option( 'clarity_wordpress_site_id', null ) ) {
					// An older backend, failed ownership callback or thrown request hook cannot repair the
					// UUID. Restore it only while the option is still empty; never replace a canonical UUID
					// persisted by the callback or a value written/deleted by a concurrent lifecycle request.
					if ( ! brandagent_wordpress_replace_site_id_if_matches( '', $wp_site_id ) ) {
						brandagent_log( 'BrandAgent WordPress Connect: stale site ID rollback skipped after concurrent option change', array( 'attempt_id' => $attempt_id ) );
					}
				}
			}

			return $retry_result;
		}

		// A remaining platform conflict means an unsupported owner or a concurrent ownership change.
		// Do not let admin_init retry it on every admin visit and create an avoidable request storm;
		// the next explicit Continue starts a fresh, authenticated attempt.
		if ( 409 === $code && 'platform_mismatch' === $error_code ) {
			brandagent_wordpress_clear_connect_retry_state();
		}

		return array(
			'success'    => false,
			'error'      => 'connect failed (status ' . $code . ')',
			'error_code' => $error_code,
		);
	}

	// By the time the server answers it has already committed this secret to Key Vault and advanced
	// the advertiser metadata, so the plugin is the only party that can still drop it. Storing can
	// fail outright (an encryption error returns false before the option is written), and a value
	// that cannot be read back leaves brandagent_get_hmac_secret() returning false while Key Vault
	// holds a secret this site cannot reproduce. Every signed BA -> plugin call then fails "Invalid
	// signature": the config update that flips BAInjectFrontendScript never lands, so the widget is
	// never injected even though the brand reports as shipped.
	//
	// Verify by reading the credential back before declaring the connect a success. This proves the
	// secret is storable and readable *now*; it cannot detect a later salt rotation, which breaks
	// decryption after the fact and is not recovered by this path. On failure leave the retry state
	// and the unverified marker intact so the throttled, attempt-capped admin_init fallback tries
	// again — each retry mints a fresh secret server-side, which converges only if this side
	// actually persists it.
	//
	// is_string, not is_scalar: json_decode turns `"hmac_secret": true` into a bool, which casts to
	// the string "1" and then stores and reads back as "1", so hash_equals below would compare "1"
	// against itself and pass. That would declare success while the site signs with "1" and Key
	// Vault holds 32 random bytes — the exact desync this guard exists to catch.
	$raw_secret = isset( $data['hmac_secret'] ) ? $data['hmac_secret'] : null;
	if ( ! is_string( $raw_secret ) ) {
		brandagent_log( 'BrandAgent WordPress Connect: hmac_secret was not a string', array(
			'store_url'  => $store_url,
			'attempt_id' => $attempt_id,
			'type'       => gettype( $raw_secret ),
		) );

		return array(
			'success'    => false,
			'error'      => 'connect failed (invalid hmac_secret)',
			'error_code' => 'invalid_hmac_secret',
		);
	}

	// A secret that normalizes to empty (e.g. all whitespace) would otherwise store and read back as
	// "" and compare equal to itself, passing the check below while leaving the site unable to sign.
	// Short-circuit so it is never written over a credential that still works.
	$migrating_from_woocommerce = 'woocommerce' === brandagent_get_hmac_platform();
	$expected_secret = str_replace( array( "\r", "\n", " " ), '', trim( $raw_secret ) );
	$secret_stored   = '' !== $expected_secret && brandagent_store_hmac_secret( $raw_secret, 'wordpress', $store_url );
	$secret_readback = $secret_stored ? brandagent_get_hmac_secret() : false;

	if ( ! $secret_stored || ! is_string( $secret_readback ) || ! hash_equals( $expected_secret, $secret_readback ) ) {
		brandagent_log( 'BrandAgent WordPress Connect: HMAC secret failed to persist', array(
			'store_url'  => $store_url,
			'attempt_id' => $attempt_id,
			'stored'     => (bool) $secret_stored,
			'readable'   => is_string( $secret_readback ),
		) );

		return array(
			'success'    => false,
			'error'      => 'HMAC secret failed to persist',
			'error_code' => 'hmac_persist_failed',
		);
	}

	// Only now is the credential confirmed to match what the server committed.
	delete_option( 'brandagent_wp_connect_unverified' );
	if ( $migrating_from_woocommerce ) {
		update_option( 'BAInjectFrontendScript', 'false' );
		delete_option( 'BAWebhooksCreated' );
	}

	update_option( 'BAOauthSuccess', true );
	brandagent_wordpress_clear_connect_retry_state();
	brandagent_log( 'BrandAgent WordPress Connect: success', array( 'store_url' => $store_url, 'attempt_id' => $attempt_id ) );

	return array(
		'success'      => true,
		'advertiserId' => isset( $data['advertiserId'] ) ? $data['advertiserId'] : null,
	);
}

/**
 * Read a safe machine-readable error code from a Dashboard Connect response.
 *
 * @param mixed $data Decoded JSON response body.
 * @return string Sanitized scalar error code, or an empty string.
 */
function brandagent_wordpress_connect_error_code( $data ) {
	if ( ! is_array( $data ) || ! isset( $data['error'] ) || ! is_scalar( $data['error'] ) ) {
		return '';
	}

	return sanitize_key( (string) $data['error'] );
}

/**
 * Build the transient key for one ownership challenge without storing the usable nonce.
 *
 * Each in-flight connect gets its own key. A single shared transient lets overlapping manual and
 * admin-resume attempts overwrite one another, making the otherwise valid callback fail with 403.
 *
 * @param string $nonce Raw one-time ownership nonce.
 * @return string Fixed-length transient key derived from the nonce.
 */
function brandagent_wordpress_connect_nonce_key( $nonce ) {
	return 'brandagent_connect_nonce_' . hash( 'sha256', (string) $nonce );
}

/**
 * Build the transient key for optional WordPress site-ID recovery context.
 *
 * @param string $nonce Raw one-time ownership nonce.
 * @return string Fixed-length transient key derived from the nonce.
 */
function brandagent_wordpress_connect_recovery_key( $nonce ) {
	return 'brandagent_connect_recovery_' . hash( 'sha256', (string) $nonce );
}

/**
 * Clear resumable-connect bookkeeping after success or a durable platform conflict.
 *
 * @return void
 */
function brandagent_wordpress_clear_connect_retry_state() {
	delete_option( 'brandagent_wp_connect_optin' );
	delete_option( 'brandagent_wp_connect_attempts' );
	delete_transient( 'brandagent_wp_connect_throttle' );

	// Part of the same bookkeeping: once nothing will retry, an unverified marker has no reader left
	// and would otherwise linger as dead state on a site that is not retrying anyway.
	delete_option( 'brandagent_wp_connect_unverified' );
}

/**
 * REST route to trigger the plain-WordPress connect from the admin UI (admin-only).
 * POST /wp-json/adsagent/v1/wordpress/connect
 */
add_action( 'rest_api_init', function () {
	register_rest_route( 'adsagent/v1', '/wordpress/connect', array(
		'methods'             => 'POST',
		'permission_callback' => function () {
			return current_user_can( 'manage_options' );
		},
		'callback'            => function () {
			$result = brandagent_wordpress_connect();
			return new WP_REST_Response( $result, ! empty( $result['success'] ) ? 200 : 502 );
		},
	) );
} );

/**
 * Store-ownership challenge for the plain-WordPress connect. The Clarity dashboard calls
 * this back with the nonce from the connect request; a match proves the connect was
 * initiated by this site's admin-privileged plugin, not forged elsewhere for this URL.
 * Public route by design — at connect time no shared secret exists yet, so the one-time
 * nonce (64 chars, 10-min TTL, consumed on match) is the proof.
 * POST /?rest_route=/adsagent/v1/wordpress/connect-verify
 */
add_action( 'rest_api_init', function () {
	register_rest_route( 'adsagent/v1', '/wordpress/connect-verify', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => 'brandagent_wordpress_connect_verify',
	) );
} );

/**
 * Verify a one-time ownership challenge and, when explicitly requested by Connect, recover the
 * existing integration UUID selected by the Clarity dashboard.
 *
 * The initiating Connect request still owns brandagent_wp_connect_lock while the dashboard makes
 * this nested callback. Do not reacquire it here: doing so would reject every legitimate callback.
 *
 * @param WP_REST_Request $request Ownership callback from the Clarity dashboard.
 * @return WP_REST_Response Verification and optional persistence acknowledgement.
 */
function brandagent_wordpress_connect_verify( WP_REST_Request $request ) {
	$received = $request->get_param( 'connectNonce' );
	$received = is_string( $received ) ? $received : '';
	$key      = brandagent_wordpress_connect_nonce_key( $received );
	$stored   = get_transient( $key );

	if ( empty( $received ) || empty( $stored ) || ! hash_equals( (string) $stored, hash( 'sha256', $received ) ) ) {
		return new WP_REST_Response( array( 'verified' => false ), 401 );
	}

	$recovery_key     = brandagent_wordpress_connect_recovery_key( $received );
	$recovery_context = get_transient( $recovery_key );

	// Consume a valid challenge before doing any recovery checks. A malformed or conflicting
	// callback must not remain replayable with different values during the nonce TTL.
	delete_transient( $key );
	delete_transient( $recovery_key );

	// Existing non-empty-ID connects have no recovery context and retain their exact legacy reply.
	if ( false === $recovery_context ) {
		return new WP_REST_Response( array( 'verified' => true ), 200 );
	}

	if (
		! is_array( $recovery_context ) ||
		! isset( $recovery_context['clarityProjectId'] ) ||
		! is_string( $recovery_context['clarityProjectId'] ) ||
		'' === $recovery_context['clarityProjectId'] ||
		1 !== preg_match( '/\A[a-zA-Z0-9]+\z/', $recovery_context['clarityProjectId'] )
	) {
		return new WP_REST_Response( array( 'verified' => false, 'error' => 'invalid_recovery_context' ), 400 );
	}

	$expected_project_id = $recovery_context['clarityProjectId'];
	$received_project_id = $request->get_param( 'clarityProjectId' );
	$current_project_id  = get_option( 'clarity_project_id', '' );
	if (
		! is_string( $received_project_id ) ||
		! is_string( $current_project_id ) ||
		! hash_equals( $expected_project_id, $received_project_id ) ||
		! hash_equals( $expected_project_id, $current_project_id )
	) {
		return new WP_REST_Response( array( 'verified' => false, 'error' => 'project_mismatch' ), 409 );
	}

	$candidate = $request->get_param( 'wordpressSiteId' );
	if ( ! is_string( $candidate ) || ! brandagent_wordpress_is_valid_site_id( $candidate ) ) {
		return new WP_REST_Response( array( 'verified' => false, 'error' => 'invalid_wordpress_site_id' ), 400 );
	}

	$current_site_id = get_option( 'clarity_wordpress_site_id', '' );
	if ( ! is_string( $current_site_id ) || ( '' !== $current_site_id && ! hash_equals( $candidate, $current_site_id ) ) ) {
		return new WP_REST_Response( array( 'verified' => false, 'error' => 'wordpress_site_id_conflict' ), 409 );
	}

	if ( '' === $current_site_id ) {
		brandagent_wordpress_persist_recovered_site_id( $candidate );
	}

	$persisted_site_id = get_option( 'clarity_wordpress_site_id', '' );
	if ( ! is_string( $persisted_site_id ) || ! hash_equals( $candidate, $persisted_site_id ) ) {
		return new WP_REST_Response( array( 'verified' => false, 'error' => 'wordpress_site_id_persist_failed' ), 500 );
	}

	return new WP_REST_Response(
		array(
			'verified'                 => true,
			'wordpressSiteIdPersisted' => true,
			'wordpressSiteId'          => $persisted_site_id,
		),
		200
	);
}

/**
 * Whether a candidate is a canonical RFC 4122 version-4 UUID.
 *
 * @param string $candidate Candidate WordPress integration ID.
 * @return bool Whether the value is a valid UUID generated by wp_generate_uuid4().
 */
function brandagent_wordpress_is_valid_site_id( $candidate ) {
	return 1 === preg_match( '/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/i', $candidate );
}

/**
 * Atomically replace an exact local site ID during a guarded recovery retry.
 *
 * Unlike recovered-ID persistence, this never inserts a missing option row: a concurrent uninstall
 * must not be undone by restoring the stale UUID after a failed retry.
 *
 * @param string $expected_site_id    Exact site ID expected in the existing option row.
 * @param string $replacement_site_id Replacement value for that row.
 * @return bool Whether the exact value was replaced.
 */
function brandagent_wordpress_replace_site_id_if_matches( $expected_site_id, $replacement_site_id ) {
	global $wpdb;
	$option_key = 'clarity_wordpress_site_id';
	$updated    = $wpdb->query( $wpdb->prepare(
		"UPDATE `{$wpdb->options}` SET `option_value` = %s WHERE `option_name` = %s AND `option_value` = %s",
		$replacement_site_id,
		$option_key,
		$expected_site_id
	) );

	if ( 1 !== $updated ) {
		return false;
	}

	wp_cache_delete( $option_key, 'options' );
	wp_cache_delete( 'alloptions', 'options' );
	wp_cache_delete( 'notoptions', 'options' );
	return true;
}

/**
 * Atomically persist a recovered site ID only while the local option remains empty.
 *
 * update_option() is a read-then-write operation and could overwrite a UUID generated by a
 * concurrent lifecycle request. The conditional update/insert below makes that race fail closed;
 * the caller always decides success from an exact readback.
 *
 * @param string $candidate Valid candidate WordPress integration ID.
 * @return void
 */
function brandagent_wordpress_persist_recovered_site_id( $candidate ) {
	global $wpdb;
	$option_key = 'clarity_wordpress_site_id';

	$updated = $wpdb->query( $wpdb->prepare(
		"UPDATE `{$wpdb->options}` SET `option_value` = %s WHERE `option_name` = %s AND `option_value` = %s",
		$candidate,
		$option_key,
		''
	) );

	if ( 0 === $updated ) {
		$wpdb->query( $wpdb->prepare(
			"INSERT IGNORE INTO `{$wpdb->options}` (`option_name`, `option_value`, `autoload`) VALUES (%s, %s, %s)",
			$option_key,
			$candidate,
			'yes'
		) );
	}

	wp_cache_delete( $option_key, 'options' );
	wp_cache_delete( 'alloptions', 'options' );
	wp_cache_delete( 'notoptions', 'options' );
}

/**
 * Whether the plugin already holds a usable Brand Agent connection.
 *
 * @return bool
 */
function brandagent_wordpress_has_connection() {
	return get_option( 'BAOauthSuccess' ) == 1 && (bool) brandagent_get_hmac_secret();
}

/**
 * Sign and send an outbound Brand Agent request using the plain-WordPress (X-WordPress-*) scheme.
 *
 * Deliberately separate from brandagent_sign_outbound_request(), which speaks the WooCommerce
 * scheme: that one signs `clientId + timestamp` and nothing else, so one captured header set is
 * replayable against any route. WordPress signs a full canonical request, binding the signature
 * to a single method, path, body and one-time nonce. The backend twin is
 * WordPressAuthUtils::BuildInboundCanonicalRequest and the two strings must stay byte-identical —
 * any divergence surfaces only as a 401, never as a useful error.
 *
 * $backend_path is the path the BRAND AGENT SERVER sees, which may differ from the URL we send
 * to when a caller still uses the Clarity dashboard proxy. Connector calls go directly to
 * AdsAgentServer; sign the AdsAgentServer path in either case. Signing the wrong path verifies
 * against the wrong string at the backend.
 *
 * @param string $proxy_url    Absolute URL to send to (the Clarity dashboard proxy route).
 * @param string $backend_path Path + query as the BA server sees it, e.g. '/api/wordpress/uninstall'.
 * @param string $body         Raw request body, or '' when there is none.
 * @param string $method       HTTP method. Default 'POST'.
 * @param int    $timeout      Timeout in seconds.
 * @return array|WP_Error wp_remote_* response, or WP_Error when no secret is available.
 */
function brandagent_wordpress_sign_outbound_request( $proxy_url, $backend_path, $body = '', $method = 'POST', $timeout = 30 ) {
	$headers = brandagent_wordpress_build_signed_headers( $backend_path, $body, $method );
	if ( is_wp_error( $headers ) ) {
		return $headers;
	}

	$method = strtoupper( $method );
	$args   = array(
		'timeout' => $timeout,
		'headers' => array_merge( array( 'Content-Type' => 'application/json' ), $headers ),
	);

	// Keep GET/POST on the original wrappers so existing Brand Agent connect,
	// uninstall, and proxy traffic is unchanged. Only new methods (DELETE) use
	// wp_remote_request().
	if ( 'GET' === $method ) {
		return wp_remote_get( $proxy_url, $args );
	}

	$args['body'] = $body;

	if ( 'POST' === $method ) {
		return wp_remote_post( $proxy_url, $args );
	}

	$args['method'] = $method;
	return wp_remote_request( $proxy_url, $args );
}

/**
 * Build the X-WordPress-* signed headers for one outbound Brand Agent request.
 *
 * Split out of brandagent_wordpress_sign_outbound_request() so callers that must drive the HTTP
 * call themselves — the SSE init proxy sets its own streaming headers and reads the response as a
 * stream — still sign through the single implementation of the canonical string. Duplicating that
 * string is the one thing to avoid here: the backend twin is
 * WordPressAuthUtils::BuildInboundCanonicalRequest and the two must stay byte-identical, so a
 * second copy that drifts would surface only as a 401 with no useful error.
 *
 * $backend_path must be path + query exactly as the BRAND AGENT SERVER receives it, because the
 * handler signs Request.Path + Request.QueryString verbatim. Callers that append a query string to
 * the outbound URL must build it once and pass the same string here.
 *
 * @param string $backend_path Path + query as the BA server sees it, e.g. '/api/v1/init?clientId=abc'.
 * @param string $body         Raw request body, or '' when there is none.
 * @param string $method       HTTP method. Default 'POST'.
 * @return array|WP_Error Header map, or WP_Error when no secret is available.
 */
function brandagent_wordpress_build_signed_headers( $backend_path, $body = '', $method = 'POST' ) {
	$secret_key = brandagent_get_hmac_secret();
	if ( empty( $secret_key ) ) {
		return new WP_Error( 'hmac_missing', 'HMAC secret key not found' );
	}

	$site_url            = brandagent_get_connected_store_url();
	$normalized_site_url = brandagent_normalize_store_url( $site_url );
	$timestamp           = (string) time();
	$nonce               = wp_generate_password( 32, false );

	// The dashboard signs with a fixed client id; a merchant's identity is the site itself, so the
	// last two canonical fields collapse to the same value here. Both are still sent because the
	// backend reads them from different headers.
	$client_id = $normalized_site_url;

	// Field order is part of the contract. See WordPressAuthUtils::BuildInboundCanonicalRequest.
	$canonical_request = implode( "\n", array(
		strtoupper( $method ),
		$backend_path,
		$timestamp,
		$nonce,
		hash( 'sha256', $body ),
		$normalized_site_url,
		$client_id,
	) );

	return array(
		'X-WordPress-Client-Id' => $client_id,
		'X-WordPress-Site-Url'  => $site_url,
		'X-WordPress-Timestamp' => $timestamp,
		'X-WordPress-Nonce'     => $nonce,
		'X-WordPress-Signature' => base64_encode( hash_hmac( 'sha256', $canonical_request, $secret_key, true ) ),
	);
}

/**
 * Tell the backend to tear down this plain-WordPress site's Brand Agent data.
 *
 * The WooCommerce twin lives in handle_brandagent_uninstall(); a WooCommerce store must keep using
 * it, because the backend's WooCommerce uninstall also unwinds credentials and webhooks that a
 * plain site never had. The two paths are not interchangeable: the per-site secret is filed under
 * a WordPress-specific Key Vault name, so a WordPress site calling the WooCommerce endpoint fails
 * signature verification and the merchant's data is silently left behind.
 *
 * @return void
 */
function brandagent_wordpress_notify_uninstall() {
	$clarity_server_url = BrandAgent_Config::get_clarity_server_url();
	if ( empty( $clarity_server_url ) ) {
		brandagent_log( 'BrandAgent WordPress Uninstall: clarity_server_url not configured; skipping backend call' );
		return;
	}

	$backend_path  = '/api/wordpress/uninstall';
	$uninstall_url = trailingslashit( $clarity_server_url ) . 'wordpress/uninstall';
	$site_url      = brandagent_get_connected_store_url();

	brandagent_log( 'BrandAgent WordPress Uninstall: calling backend', array( 'site_url' => $site_url, 'endpoint' => $uninstall_url ) );

	// Empty body on purpose. The signature covers sha256(body), and the dashboard proxy re-serializes
	// anything it parses — PHP escapes forward slashes in JSON and JSON.stringify does not, so a body
	// carrying the site URL would arrive with a different hash and fail verification. The backend
	// reads the site from the signed X-WordPress-Site-Url header, which survives the hop intact.
	//
	// Short timeout: this runs inside the uninstall hook while the admin waits on the delete, and the
	// local teardown must happen whether or not the backend answers.
	$response = brandagent_wordpress_sign_outbound_request( $uninstall_url, $backend_path, '', 'POST', 15 );

	if ( is_wp_error( $response ) ) {
		brandagent_log( 'BrandAgent WordPress Uninstall: backend call failed', array( 'error' => $response->get_error_message() ) );
		return;
	}

	brandagent_log( 'BrandAgent WordPress Uninstall: backend returned', array( 'status_code' => wp_remote_retrieve_response_code( $response ) ) );
}

/**
 * admin-ajax entry point that lets the Clarity dashboard (embedded in the wp-admin
 * iframe) trigger the plain-WordPress connect from the Brand Agent setup choice.
 *
 * The dashboard posts a WORDPRESS_CONNECT message to wp-admin; js/add_window_listeners.js
 * forwards it here with the same admin nonce the project-id handler uses. We re-verify
 * that nonce and the admin capability before running the server-to-server connect, then
 * return JSON the listener relays back to the iframe as WORDPRESS_CONNECT_SUCCESS/FAILURE.
 *
 * Clicking Continue is the opt-in: we record it so a disconnected site can finish the
 * connect server-side on later admin loads (see brandagent_wordpress_maybe_resume_connect),
 * mirroring the pilot auto-connect resilience but without ever connecting a site that never
 * opted in from the setup choice.
 */
add_action( 'wp_ajax_brandagent_wordpress_connect', 'brandagent_wordpress_connect_ajax' );
function brandagent_wordpress_connect_ajax() {
	$nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
	if ( ! wp_verify_nonce( $nonce, 'wp_ajax_edit_clarity_project_id' ) ) {
		wp_send_json( array( 'success' => false, 'error' => 'Invalid nonce.' ) );
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json( array( 'success' => false, 'error' => 'User must be a WordPress admin.' ) );
	}

	// Checked here as well as inside brandagent_wordpress_connect() so a WooCommerce store never
	// records plain-WordPress connect bookkeeping it would then retry from admin_init.
	if ( clarity_is_woocommerce_active_for_current_blog() ) {
		wp_send_json( array( 'success' => false, 'error' => 'WooCommerce site must use the WooCommerce connect flow.' ) );
	}

	$result = brandagent_wordpress_connect( $_POST );
	wp_send_json( $result );
}

/**
 * Persist the project selected by the dashboard before starting WordPress Connect.
 *
 * Older dashboard versions do not send project_id, so its absence deliberately preserves the
 * legacy flow. The caller must own brandagent_wp_connect_lock: when the field is present, the exact
 * value must read back and remain protected until the ensuing Connect has consumed it.
 *
 * @param array $request AJAX request data.
 * @return array Result with at least a boolean 'success' key.
 */
function brandagent_wordpress_prepare_connect_project_id( $request ) {
	if ( ! is_array( $request ) || ! array_key_exists( 'project_id', $request ) ) {
		return array( 'success' => true );
	}

	$raw_project_id = $request['project_id'];
	if ( ! is_string( $raw_project_id ) ) {
		return array(
			'success'    => false,
			'error'      => 'Invalid Clarity project ID.',
			'error_code' => 'invalid_project_id',
		);
	}

	$raw_project_id = wp_unslash( $raw_project_id );
	$project_id     = sanitize_text_field( $raw_project_id );
	if ( $project_id !== $raw_project_id || '' === $project_id || 1 !== preg_match( '/\A[a-zA-Z0-9]+\z/', $project_id ) ) {
		return array(
			'success'    => false,
			'error'      => 'Invalid Clarity project ID.',
			'error_code' => 'invalid_project_id',
		);
	}

	update_option( 'clarity_project_id', $project_id );
	if ( $project_id !== get_option( 'clarity_project_id', '' ) ) {
		return array(
			'success'    => false,
			'error'      => 'Unable to save the Clarity project ID.',
			'error_code' => 'project_id_persist_failed',
		);
	}

	return array( 'success' => true );
}

/**
 * Button-initiated connect resilience — the trunk equivalent of the pilot auto-connect.
 *
 * After the admin opts in from the Brand Agent setup choice (Continue), finish the
 * plain-WordPress connect server-side on later admin page loads if the browser round trip did
 * not complete it (e.g. the iframe closed before the reply, or the postMessage was dropped).
 * Unlike the pilot this never runs before the admin opts in, so it is not an automatic connect.
 * Gated to plain WordPress, throttled to one attempt every few minutes, and attempt-capped so a
 * persistently failing backend cannot hammer the Clarity server.
 *
 * @return void
 */
add_action( 'admin_init', 'brandagent_wordpress_maybe_resume_connect' );
function brandagent_wordpress_maybe_resume_connect() {
	// admin_init also runs during admin-ajax.php. The explicit AJAX handler below owns that request;
	// starting a hidden resume first creates two connect attempts from one click.
	if ( wp_doing_ajax() ) {
		return;
	}

	// WooCommerce stores use the wc-auth onboarding flow and must never take this path.
	if ( clarity_is_woocommerce_active_for_current_blog() ) {
		return;
	}

	// Only after the admin clicked Continue on the Brand Agent setup choice.
	if ( ! get_option( 'brandagent_wp_connect_optin' ) ) {
		return;
	}

	// Already connected: clear the opt-in bookkeeping and stop. The unverified marker is what keeps
	// this from firing after a connect that may have rotated the server-side secret without this
	// site persisting the replacement — a stale credential still decrypts, so has_connection() alone
	// would report a live connection and cancel the very retries needed to converge.
	if ( brandagent_wordpress_has_connection() && ! get_option( 'brandagent_wp_connect_unverified' ) ) {
		brandagent_wordpress_clear_connect_retry_state();
		return;
	}

	// Throttle: at most one server-side attempt per window across admin page loads.
	if ( get_transient( 'brandagent_wp_connect_throttle' ) ) {
		return;
	}

	// Attempt cap: give up (until the next Continue) after a bounded number of tries.
	$attempts = (int) get_option( 'brandagent_wp_connect_attempts', 0 );
	if ( $attempts >= 5 ) {
		brandagent_wordpress_clear_connect_retry_state();
		return;
	}

	set_transient( 'brandagent_wp_connect_throttle', 1, 2 * MINUTE_IN_SECONDS );

	brandagent_log( 'BrandAgent WordPress Connect: server-side resume attempt', array( 'attempt' => $attempts + 1 ) );
	$result     = brandagent_wordpress_connect();
	$error_code = isset( $result['error_code'] ) && is_scalar( $result['error_code'] ) ? (string) $result['error_code'] : '';

	// A request that lost the lock never reached Connect, so it must not spend the bounded retry
	// budget. Successful and durable-conflict paths clear opt-in state inside Connect.
	if ( empty( $result['success'] ) && 'connect_in_progress' !== $error_code && get_option( 'brandagent_wp_connect_optin' ) ) {
		update_option( 'brandagent_wp_connect_attempts', (int) get_option( 'brandagent_wp_connect_attempts', 0 ) + 1 );
	}
}
