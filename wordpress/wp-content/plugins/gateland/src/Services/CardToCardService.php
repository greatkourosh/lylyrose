<?php

namespace Nabik\Gateland\Services;

use Nabik\Gateland\Gateways\BaseGateway;
use Nabik\Gateland\Gateways\CardToCardGateway;
use Nabik\Gateland\Helper;
use Nabik\Gateland\Models\Gateway;
use Nabik\Gateland\Models\Receipt;
use Nabik\Gateland\Models\Transaction;

class CardToCardService {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'menu_receipts_count' ], 100 );
		add_action( 'nabik/gateland/receipt_status_changed', [ $this, 'reset_pending_receipts_count' ] );
		add_filter( 'intermediate_image_sizes_advanced', [ $this, 'prevent_receipt_images_make_subsizes' ], 10, 3 );
	}

	public function menu_receipts_count() {
		global $menu, $submenu;

		$receipts_count = self::pending_receipts_count();

		if ( $receipts_count ) {

			$receipts_count = Helper::fa_num( number_format( $receipts_count ) );
			$receipts_count = ' <span class="update-plugins"><span class="processing-count">' . $receipts_count . '</span></span>';

			foreach ( $menu as &$menu_item ) {
				if ( $menu_item[2] == 'gateland' ) {
					$menu_item[0] .= $receipts_count;
					break;
				}
			}

			foreach ( $submenu['gateland'] ?? [] as $key => $submenu_item ) {

				if ( $submenu_item[2] == 'gateland-receipts' ) {
					$submenu['gateland'][ $key ][0] .= $receipts_count;
					break;
				}

			}

		}
	}

	public static function pending_receipts_count(): int {

		$count = get_transient( 'gateland_pending_receipts_count' );

		if ( $count !== false ) {
			return $count;
		} elseif ( \Nabik_Net_Database::Schema()->hasTable( 'gateland_receipts' ) ) {
			$count = Receipt::query()
			                ->where( 'status', 'pending' )
			                ->count();
		} else {
			$count = 0;
		}

		set_transient( 'gateland_pending_receipts_count', $count, HOUR_IN_SECONDS );

		return $count;
	}

	public static function reset_pending_receipts_count() {
		delete_transient( 'gateland_pending_receipts_count' );
	}

	/**
	 * @return BaseGateway
	 * @throws \Exception
	 */
	public static function get_gateway(): BaseGateway {

		/** @var Gateway $gateway */
		$gateway = Gateway::query()
		                  ->where( 'class', CardToCardGateway::class )
		                  ->first();

		if ( is_null( $gateway ) ) {
			throw new \Exception( 'درگاه کارت به کارت یافت نشد.' );
		}

		return $gateway->build();
	}

	/**
	 * @throws \Exception
	 */
	public static function upload_receipt( Transaction $transaction ): int {

		if ( ! function_exists( 'media_handle_upload' ) ) {
			require_once( ABSPATH . 'wp-admin/includes/file.php' );
			require_once( ABSPATH . 'wp-admin/includes/image.php' );
			require_once( ABSPATH . 'wp-admin/includes/media.php' );
		}

		if ( ! isset( $_FILES['receipt'] ) ) {
			throw new \Exception( 'ارسال تصویر رسید الزامی می‌باشد.' );
		}

		$gateway = self::get_gateway();

		$max_file_size = $gateway->options['max_file_size'];
		$file_size     = $_FILES['receipt']['size'] / 1024 / 1000;

		if ( $file_size > $max_file_size ) {
			throw new \Exception( sprintf( 'تصویر رسید ارسالی %.2f مگابایت است. حداکثر حجم مجاز تصویر %.2f مگابایت است.', $file_size, $max_file_size ) );
		}

		$overrides = [
			'test_form'                => false,
			'unique_filename_callback' => function ( $dir, $name, $ext ) {
				return md5( $name . NONCE_SALT ) . $ext;
			},
			'mimes'                    => [
				// From wp_get_mime_types()
				'jpg|jpeg|jpe' => 'image/jpeg',
				'png'          => 'image/png',
			],
		];

		add_filter( 'upload_dir', [ CardToCardService::class, 'gateland_upload_dir' ] );

		$attachment_id = \media_handle_upload( 'receipt', 0, [
			'post_author'  => get_current_user_id(),
			'post_title'   => sprintf( 'تراکنش: %d', $transaction->id ),
			'post_excerpt' => sprintf( 'سفارش: %d', $transaction->order_id ),
			'post_content' => sprintf( 'پذیرنده: %s', $transaction->client_label ),
		], $overrides );

		remove_filter( 'upload_dir', [ CardToCardService::class, 'gateland_upload_dir' ] );

		if ( is_wp_error( $attachment_id ) ) {
			throw new \Exception( $attachment_id->get_error_message() );
		}

		return $attachment_id;
	}

	public static function gateland_upload_dir( array $uploads ): array {

		if ( empty( $uploads['subdir'] ) ) {
			$uploads['path']   = $uploads['path'] . '/woocommerce_uploads';
			$uploads['url']    = $uploads['url'] . '/woocommerce_uploads';
			$uploads['subdir'] = '/gateland/receipts';
		} else {
			$new_subdir = '/gateland/receipts' . $uploads['subdir'];

			$uploads['path']   = str_replace( $uploads['subdir'], $new_subdir, $uploads['path'] );
			$uploads['url']    = str_replace( $uploads['subdir'], $new_subdir, $uploads['url'] );
			$uploads['subdir'] = str_replace( $uploads['subdir'], $new_subdir, $uploads['subdir'] );
		}

		return $uploads;
	}

	public function prevent_receipt_images_make_subsizes( array $new_sizes, array $image_meta, int $attachment_id ): array {

		if ( str_contains( $image_meta['file'], 'gateland/receipts' ) ) {
			return [];
		}

		return $new_sizes;
	}
}