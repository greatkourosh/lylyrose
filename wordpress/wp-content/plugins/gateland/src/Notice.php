<?php

namespace Nabik\Gateland;

use DOMDocument;

defined( 'ABSPATH' ) || exit;

class Notice {

	public function __construct() {
		add_action( 'admin_notices', [ $this, 'admin_notices' ], 5 );
		add_action( 'wp_ajax_gateland_dismiss_notice', [ $this, 'dismiss_notice' ] );
		add_action( 'wp_ajax_gateland_update_notice', [ $this, 'update_notice' ] );
	}

	public function admin_notices() {

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( $this->is_dismiss( 'all' ) ) {
			return;
		}

		foreach ( $this->notices() as $notice ) {

			if ( ! $notice['condition'] || $this->is_dismiss( $notice['id'] ) ) {
				continue;
			}

			$dismissible    = $notice['dismiss'] ? 'is-dismissible' : '';
			$notice_content = strip_tags( $notice['content'], '<p><a><b><img><ul><ol><li>' );

			printf(
				'<div class="notice gateland_notice notice-success %s" id="gateland_%s"><p>%s</p></div>',
				esc_attr( $dismissible ),
				esc_attr( $notice['id'] ),
				$notice_content
			);

			break;
		}

		?>
		<script type="text/javascript">
            jQuery(document).ready(function ($) {

                $(document.body).on('click', '.notice-dismiss', function () {

                    let notice = $(this).closest('.gateland_notice');
                    notice = notice.attr('id');

                    if (notice !== undefined && notice.indexOf('gateland_') !== -1) {

                        notice = notice.replace('gateland_', '');

                        $.ajax({
                            url: "<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>",
                            type: 'post',
                            data: {
                                notice: notice,
                                action: 'gateland_dismiss_notice',
                                nonce: "<?php echo esc_attr( wp_create_nonce( 'gateland_dismiss_notice' ) ); ?>"
                            }
                        });
                    }

                });

            });
		</script>
		<?php

		if ( get_transient( 'gateland_update_notices' ) ) {
			return;
		}

		?>
		<script type="text/javascript">
            jQuery(document).ready(function ($) {

                jQuery.ajax({
                    url: "<?php echo esc_url( admin_url( 'admin-ajax.php' ) ) ?>",
                    type: 'post',
                    data: {
                        action: 'gateland_update_notice',
                        nonce: '<?php echo esc_attr( wp_create_nonce( 'gateland_update_notice' ) ); ?>'
                    }
                });

            });
		</script>
		<?php
	}

	public function notices(): array {
		global $pagenow;

		$page = sanitize_text_field( $_GET['page'] ?? null );
		$tab  = sanitize_text_field( $_GET['tab'] ?? null );

		$notices = [

//			[
//				'id'        => 'gateland_video',
//				'content'   => '<b>آموزش:</b> برای پیکربندی گیت‌لند می توانید از <a href="https://yun.ir/gatelandvideo" target="_blank">اینجا</a> فیلم های آموزشی افزونه را مشاهده کنید.',
//				'condition' => 1,
//				'dismiss'   => 6 * MONTH_IN_SECONDS,
//			],

		];

		$_notices = get_option( 'gateland_notices', [] );

		foreach ( $_notices['notices'] ?? [] as $_notice ) {

			$_notice['condition'] = 1;

			$rules = $_notice['rules'];

			if ( isset( $rules['pagenow'] ) && $rules['pagenow'] != $pagenow ) {
				$_notice['condition'] = 0;
			}

			if ( isset( $rules['page'] ) && $rules['page'] != $page ) {
				$_notice['condition'] = 0;
			}

			if ( isset( $rules['tab'] ) && $rules['tab'] != $tab ) {
				$_notice['condition'] = 0;
			}

			if ( isset( $rules['active'] ) && is_plugin_inactive( $rules['active'] ) ) {
				$_notice['condition'] = 0;
			}

			if ( isset( $rules['inactive'] ) && is_plugin_active( $rules['inactive'] ) ) {
				$_notice['condition'] = 0;
			}

			unset( $_notice['rules'] );

			array_unshift( $notices, $_notice );
		}

		return $notices;
	}

	public function dismiss_notice() {

		check_ajax_referer( 'gateland_dismiss_notice', 'nonce' );

		$this->set_dismiss( sanitize_text_field( $_POST['notice'] ) );

		die();
	}

	public function update_notice() {

		$update = get_transient( 'gateland_update_notices' );

		if ( $update ) {
			return;
		}

		set_transient( 'gateland_update_notices', 1, DAY_IN_SECONDS / 10 );

		check_ajax_referer( 'gateland_update_notice', 'nonce' );

		$notices = wp_remote_get( 'https://wpnotice.ir/gateland.json', [ 'timeout' => 5, ] );
		$sign    = wp_remote_get( 'https://wphash.ir/gateland.hash', [ 'timeout' => 5, ] );

		if ( is_wp_error( $notices ) || is_wp_error( $sign ) ) {
			die();
		}

		if ( ! is_array( $notices ) || ! is_array( $sign ) ) {
			die();
		}

		$notices = trim( $notices['body'] );
		$sign    = trim( $sign['body'] );

		if ( sha1( $notices ) !== $sign ) {
			die();
		}

		$notices = json_decode( $notices, JSON_OBJECT_AS_ARRAY );

		if ( empty( $notices ) || ! is_array( $notices ) ) {
			die();
		}

		foreach ( $notices['notices'] as &$_notice ) {

			$doc     = new DOMDocument();
			$content = strip_tags( $_notice['content'], '<p><a><b><img><ul><ol><li>' );
			$content = str_replace( [ 'javascript', 'java', 'script' ], '', $content );
			$doc->loadHTML( mb_convert_encoding( $content, 'HTML-ENTITIES', 'UTF-8' ) );

			foreach ( $doc->getElementsByTagName( '*' ) as $element ) {

				$href  = null;
				$src   = null;
				$style = $element->getAttribute( 'style' );

				if ( $element->nodeName == 'a' ) {
					$href = $element->getAttribute( 'href' );
				}

				if ( $element->nodeName == 'img' ) {
					$src = $element->getAttribute( 'src' );
				}

				foreach ( $element->attributes as $attribute ) {
					$element->removeAttribute( $attribute->name );
				}

				if ( $href && filter_var( $href, FILTER_VALIDATE_URL ) ) {
					$element->setAttribute( 'href', $href );
					$element->setAttribute( 'target', '_blank' );
				}

				if ( $src && filter_var( $src, FILTER_VALIDATE_URL ) && strpos( $src, 'https://repo.nabik.net' ) === 0 ) {
					$element->setAttribute( 'src', $src );
				}

				if ( $style ) {
					$element->setAttribute( 'style', $style );
				}
			}

			$_notice['content'] = $doc->saveHTML();
		}

		update_option( 'gateland_notices', $notices );

		die();
	}

	public function set_dismiss( string $notice_id ) {

		$notices = wp_list_pluck( $this->notices(), 'dismiss', 'id' );

		if ( isset( $notices[ $notice_id ] ) && $notices[ $notice_id ] ) {
			update_option( 'gateland_dismiss_notice_' . $notice_id, time() + intval( $notices[ $notice_id ] ), 'yes' );
			update_option( 'gateland_dismiss_notice_all', time() + DAY_IN_SECONDS );
		}
	}

	public function is_dismiss( $notice_id ): bool {
		return intval( get_option( 'gateland_dismiss_notice_' . $notice_id ) ) >= time();
	}

}
