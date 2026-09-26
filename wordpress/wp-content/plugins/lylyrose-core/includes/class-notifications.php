<?php
/**
 * Notifications center (اعلانها).
 *
 * Custom post type `asc_notification` for logged-in customers:
 * - Bell icon in header with unread-badge and dropdown (5 most recent).
 * - Account endpoint `/my-account/notifications/` listing all notifications.
 * - Automatic producers: order status change, product review replies.
 *
 * @package lylyrose-core
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ASC_Notifications {

    const VERSION = '2.1.0';

    static $instance = null;

    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Post type registration
        add_action( 'init', array( $this, 'register_post_type' ), 25 );

        // Add to WC query vars (for endpoint recognition)
        add_filter( 'woocommerce_get_query_vars', array( $this, 'add_query_var' ) );

        // Rewrite endpoint for my-account/notifications/
        add_action( 'init', array( $this, 'add_endpoint' ), 30 );

        // Self-heal rewrite flush on version bump
        add_action( 'init', array( $this, 'maybe_flush_rewrite' ), 99 );

        // AJAX handlers (logged-in only)
        add_action( 'wp_ajax_asc_notifications_unread', array( $this, 'ajax_unread_count' ) );
        add_action( 'wp_ajax_asc_notifications_mark_read', array( $this, 'ajax_mark_read' ) );

        // Account endpoint renderer (WC fires woocommerce_account_{endpoint}_endpoint)
        add_action( 'woocommerce_account_notifications_endpoint', array( $this, 'render_account_page' ) );

        // Producers: order status changes
        add_action( 'woocommerce_order_status_changed', array( $this, 'on_order_status_change' ), 10, 3 );

        // Producers: review replies
        add_action( 'comment_post', array( $this, 'on_comment_post' ), 10, 3 );
    }

    /**
     * Add notifications to WC's account query vars.
     *
     * @param array $vars Existing query vars.
     * @return array Modified query vars.
     */
    public function add_query_var( $vars ) {
        $vars['notifications'] = 'notifications';
        return $vars;
    }

    /**
     * Register the asc_notification post type.
     */
    public function register_post_type() {
        register_post_type( 'asc_notification', array(
            'label'  => __( 'Notifications', 'lylyrose-core' ),
            'public' => false,
            'show_ui' => false,
            'show_in_menu' => false,
            'show_in_admin_bar' => false,
            'show_in_rest' => false,
            'hierarchical' => false,
            'supports' => array( 'title', 'custom-fields' ),
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'rewrite' => false,
        ) );
    }

    /**
     * Add the notifications rewrite endpoint.
     */
    public function add_endpoint() {
        add_rewrite_endpoint( 'notifications', EP_ROOT | EP_PAGES );
    }

    /**
     * Flush rewrite rules once on version bump (self-heal).
     */
    public function maybe_flush_rewrite() {
        $version = get_option( 'asc_notifications_version', '' );
        if ( $version !== self::VERSION ) {
            $this->add_endpoint();
            flush_rewrite_rules();
            update_option( 'asc_notifications_version', self::VERSION );
        }
    }

    /**
     * Create a notification for a user.
     *
     * @param int    $user_id    Target user ID.
     * @param string $title      Notification title (Persian text).
     * @param string $url        Target URL (stored in post_content).
     * @return int|WP_Error      Post ID or error.
     */
    public function create( $user_id, $title, $url ) {
        $post_id = wp_insert_post( array(
            'post_type'    => 'asc_notification',
            'post_author'  => (int) $user_id,
            'post_title'   => $title,
            'post_content' => $url,
            'post_status'  => 'publish',
        ) );
        return $post_id;
    }

    /**
     * Get unread notification count for a user.
     *
     * @param int $user_id User ID.
     * @return int Unread count.
     */
    public function get_unread_count( $user_id ) {
        $unread = get_posts( array(
            'post_type'      => 'asc_notification',
            'post_status'    => 'publish',
            'author'         => (int) $user_id,
            'meta_query'     => array(
                array(
                    'key'     => '_asc_notification_read',
                    'compare' => 'NOT EXISTS',
                ),
            ),
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'posts_per_page' => -1,
        ) );
        return count( $unread );
    }

    /**
     * Get recent notifications for a user.
     *
     * @param int $user_id  User ID.
     * @param int $limit    Max notifications to return.
     * @return array        Array of post objects.
     */
    public function get_recent( $user_id, $limit = 5 ) {
        return get_posts( array(
            'post_type'      => 'asc_notification',
            'post_status'    => 'publish',
            'author'         => (int) $user_id,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'posts_per_page' => (int) $limit,
        ) );
    }

    /**
     * Mark a single notification as read.
     *
     * @param int $id Notification post ID.
     * @return bool Success.
     */
    public function mark_read( $id ) {
        $post = get_post( $id );
        if ( ! $post || $post->post_type !== 'asc_notification' ) {
            return false;
        }
        // Verify ownership
        if ( get_current_user_id() !== (int) $post->post_author ) {
            return false;
        }
        return update_post_meta( $id, '_asc_notification_read', 1 );
    }

    /**
     * Mark all notifications as read for a user.
     *
     * @param int $user_id User ID.
     * @return int Count marked.
     */
    public function mark_all_read( $user_id ) {
        $notifications = get_posts( array(
            'post_type'      => 'asc_notification',
            'post_status'    => 'publish',
            'author'         => (int) $user_id,
            'fields'         => 'ids',
            'posts_per_page' => -1,
        ) );
        $count = 0;
        foreach ( $notifications as $id ) {
            if ( update_post_meta( $id, '_asc_notification_read', 1 ) ) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * AJAX handler: get unread count.
     */
    public function ajax_unread_count() {
        check_ajax_referer( 'asc_notifications', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'وارد حساب خود شوید.', 'lylyrose-core' ) ), 403 );
        }

        $count = $this->get_unread_count( get_current_user_id() );
        wp_send_json_success( array( 'count' => $count ) );
    }

    /**
     * AJAX handler: mark notification(s) as read.
     */
    public function ajax_mark_read() {
        check_ajax_referer( 'asc_notifications', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => __( 'وارد حساب خود شوید.', 'lylyrose-core' ) ), 403 );
        }

        $id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;

        if ( $id ) {
            $success = $this->mark_read( $id );
        } else {
            $success = $this->mark_all_read( get_current_user_id() );
        }

        wp_send_json_success( array( 'success' => (bool) $success ) );
    }

    /**
     * Render the my-account/notifications/ page.
     */
    public function render_account_page() {
        $user_id = get_current_user_id();

        if ( ! $user_id ) {
            echo '<p>' . esc_html__( 'برای مشاهده اعلان‌ها وارد حساب خود شوید.', 'lylyrose-core' ) . '</p>';
            return;
        }

        // Mark all as read (viewing the page clears unread)
        $this->mark_all_read( $user_id );

        // Get all notifications
        $notifications = get_posts( array(
            'post_type'      => 'asc_notification',
            'post_status'    => 'publish',
            'author'         => $user_id,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'posts_per_page' => 20,
        ) );
        ?>

        <div class="dk-account-page-section">
            <h2 class="dk-cart-title"><?php esc_html_e( 'اعلان‌ها', 'lylyrose-core' ); ?></h2>

            <?php if ( empty( $notifications ) ) : ?>
                <div class="dk-notifications-empty">
                    <p><?php esc_html_e( 'هنوز اعلانی ندارید.', 'lylyrose-core' ); ?></p>
                </div>
            <?php else : ?>
                <div class="dk-notifications-wrapper">
                    <ul class="dk-notifications">
                        <?php foreach ( $notifications as $n ) :
                            $is_unread = '' === get_post_meta( $n->ID, '_asc_notification_read', true );
                            ?>
                            <li class="dk-notifications-item<?php echo $is_unread ? ' is-unread' : ''; ?>">
                                <a href="<?php echo esc_url( $n->post_content ); ?>" class="dk-notifications-link">
                                    <?php echo esc_html( $n->post_title ); ?>
                                </a>
                                <span class="dk-notifications-date">
                                    <?php echo sprintf( __( '%s پیش', 'lylyrose-core' ), human_time_diff( strtotime( $n->post_date ), current_time( 'timestamp' ) ) ); ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <div class="dk-notifications-actions">
                        <button type="button" class="dk-bell-mark-all" data-action="mark-all-read">
                            <?php esc_html_e( 'علامتگذاری همه به عنوان خوانده‌شده', 'lylyrose-core' ); ?>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Get Persian label for order status.
     *
     * @param string $status Status slug.
     * @return string Persian label.
     */
    private function get_status_label( $status ) {
        $labels = array(
            'processing'  => __( 'در حال پردازش', 'lylyrose-core' ),
            'completed'   => __( 'تکمیل شده', 'lylyrose-core' ),
            'on-hold'     => __( 'در انتظار پرداخت', 'lylyrose-core' ),
            'cancelled'   => __( 'لغو شده', 'lylyrose-core' ),
        );
        return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
    }

    /**
     * Producer: on order status change, notify the customer.
     *
     * @param int    $order_id   Order ID.
     * @param string $old_status Old status.
     * @param string $new_status New status.
     */
    public function on_order_status_change( $order_id, $old_status, $new_status ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $user_id = (int) $order->get_user_id();
        if ( ! $user_id ) {
            return; // guest order
        }

        $valid_statuses = array( 'processing', 'completed', 'on-hold', 'cancelled' );
        if ( ! in_array( $new_status, $valid_statuses, true ) ) {
            return;
        }

        $status_label = $this->get_status_label( $new_status );
        $order_num    = $order->get_order_number();
        $order_url    = $order->get_view_order_url();

        $this->create( $user_id,
            sprintf( __( 'سفارش #%s به وضعیت "%s" تغییر کرد', 'lylyrose-core' ), $order_num, $status_label ),
            $order_url
        );
    }

    /**
     * Producer: on comment reply to a product review, notify the original reviewer.
     *
     * @param int     $comment_id      Comment ID.
     * @param int     $comment_approved Approval status.
     * @param array   $comment_data    Comment data.
     */
    public function on_comment_post( $comment_id, $comment_approved, $comment_data ) {
        if ( ! $comment_approved ) {
            return;
        }

        // Only replies (parent > 0)
        if ( ! isset( $comment_data['comment_parent'] ) || (int) $comment_data['comment_parent'] <= 0 ) {
            return;
        }

        $parent = get_comment( (int) $comment_data['comment_parent'] );
        if ( ! $parent ) {
            return;
        }

        // Parent must be a review on a product
        $parent_post = get_post( $parent->comment_post_ID );
        if ( ! $parent_post || 'product' !== $parent_post->post_type ) {
            return;
        }
        if ( 'review' !== $parent->comment_type ) {
            return;
        }

        // Find original reviewer by email
        $reviewer = get_user_by( 'email', $parent->comment_author_email );
        if ( ! $reviewer ) {
            return;
        }

        // Current user is the replier
        $replier = wp_get_current_user();
        if ( ! $replier || ! $replier->ID ) {
            return; // anonymous reply not supported
        }

        $product_title = get_the_title( $parent->comment_post_ID );
        $product_url   = get_permalink( $parent->comment_post_ID );

        $this->create( $reviewer->ID,
            sprintf( __( 'کاربر «%s» به نظر شما در «%s» پاسخ داد', 'lylyrose-core' ), $replier->display_name, $product_title ),
            $product_url
        );
    }
}
