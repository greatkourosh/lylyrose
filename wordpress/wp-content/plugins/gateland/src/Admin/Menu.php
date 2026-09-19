<?php

namespace Nabik\Gateland\Admin;

use Nabik\GatelandPro\Services\CardToCardService;

defined( 'ABSPATH' ) || exit;

class Menu {

	public string $plugin_file = 'gateland/gateland.php';

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'admin_menu' ], 20 );
		add_action( 'admin_head', [ $this, 'admin_head' ], 20 );
		add_filter( 'plugin_action_links_' . $this->plugin_file, [ $this, 'settings_action' ], 100 );
		add_filter( 'plugin_row_meta', [ $this, 'plugin_row_meta' ], 10, 2 );

		Settings::instance();
	}

	public function admin_menu() {
		global $admin_page_hooks;

		$capability = apply_filters( 'nabik_menu_capability', 'manage_options' );

		if ( ! isset( $admin_page_hooks['nabik'] ) ) {
			add_menu_page( 'نابیک', 'نابیک', $capability, 'nabik', null, GATELAND_URL . 'assets/images/nabik.png', '55.9' );
		}

		$capability = apply_filters( 'nabik/gateland/menu_capability', 'manage_options' );

		add_menu_page( 'گیت‌لند', 'گیت‌لند', $capability, 'gateland', null, GATELAND_URL . 'assets/images/gateland.png', '55.19' );

		$submenus = [
			10 => [
				'title'      => 'پیشخوان',
				'capability' => $capability,
				'slug'       => 'gateland',
				'callback'   => function () {
					include GATELAND_DIR . '/templates/admin/dashboard.php';
				},
			],
			20 => [
				'title'      => 'تراکنش‌ها',
				'capability' => $capability,
				'slug'       => 'gateland-transactions',
				'callback'   => function () {
					include GATELAND_DIR . '/templates/admin/transactions.php';
				},
			],
			30 => [
				'title'      => 'درگاه‌ها',
				'capability' => $capability,
				'slug'       => 'gateland-gateways',
				'callback'   => function () {
					include GATELAND_DIR . '/templates/admin/gateways.php';
				},
			],
			50 => [
				'title'      => 'افزونه‌ها',
				'capability' => $capability,
				'slug'       => 'gateland-plugins',
				'callback'   => function () {
					include GATELAND_DIR . '/templates/admin/plugins.php';
				},
			],
			60 => [
				'title'      => 'رسیدها',
				'capability' => $capability,
				'slug'       => 'gateland-receipts',
				'callback'   => function () {
					include GATELAND_DIR . '/templates/admin/receipts.php';
				},
			],
			70 => [
				'title'      => 'کارت‌ها',
				'capability' => $capability,
				'slug'       => 'gateland-cards',
				'callback'   => function () {
					include GATELAND_DIR . '/templates/admin/cards.php';
				},
			],
			80 => [
				'title'      => 'تنظیمات',
				'capability' => $capability,
				'slug'       => 'gateland-settings',
				'callback'   => [ 'Nabik\Gateland\Admin\Settings', 'output' ],
			],
		];

		if ( ! defined( 'GATELAND_PRO_VERSION' ) ) {
			$submenus[] = [
				'title'      => 'نسخه حرفه‌ای',
				'capability' => $capability,
				'slug'       => 'link-to-gateland-pro',
				'callback'   => '',
			];
		}

		$submenus = apply_filters( 'nabik/gateland/submenus', $submenus );

		foreach ( $submenus as $submenu ) {
			add_submenu_page( 'gateland', $submenu['title'], $submenu['title'], $submenu['capability'], $submenu['slug'], $submenu['callback'] );
		}

		add_submenu_page( 'gateland-pages', 'افزودن درگاه', 'افزودن درگاه', $capability, 'gateland-gateways-add', function () {
			include GATELAND_DIR . '/templates/admin/gateways-add.php';
		} );

		add_submenu_page( 'gateland-pages', 'ویرایش درگاه', 'ویرایش درگاه', $capability, 'gateland-gateways-edit', function () {
			include GATELAND_DIR . '/templates/admin/gateways-edit.php';
		} );

		add_submenu_page( 'gateland-pages', 'مشاهده درگاه', 'مشاهده درگاه', $capability, 'gateland-gateway', function () {
			include GATELAND_DIR . '/templates/admin/gateway.php';
		} );

		add_submenu_page( 'gateland-pages', 'مشاهده تراکنش', 'مشاهده تراکنش', $capability, 'gateland-transaction', function () {
			include GATELAND_DIR . '/templates/admin/transaction.php';
		} );

		add_submenu_page( 'gateland-pages', 'مشاهده رسید', 'مشاهده رسید', $capability, 'gateland-receipt', function () {
			include GATELAND_DIR . '/templates/admin/receipt.php';
		} );
	}

	public function admin_head() {
		?>
		<script type="text/javascript">
            jQuery(document).ready(function ($) {
                $("#toplevel_page_gateland a[href$='link-to-gateland-pro']")
                    .attr('href', 'https://l.nabik.net/gateland-pro?utm_source=menu')
                    .attr('target', '_blank');
            });
		</script>
		<?php
	}

	public function settings_action( array $actions ): array {

		$actions['settings'] = sprintf( '<a href="%s" target="blank">%s</a>', admin_url( 'admin.php?page=gateland-settings' ), 'تنظیمات' );

		$brand = [
			'nabik' => sprintf( '<a href="%s" target="blank" style="background: rgb(247, 181, 52);color: white;padding: 0px 5px;border-radius: 2px;">%s</a>', 'https://nabik.net', 'نابیک' ),
		];

		return $brand + $actions;
	}

	public function plugin_row_meta( array $plugin_meta, $plugin_file ): array {

		if ( $plugin_file != $this->plugin_file ) {
			return $plugin_meta;
		}

		$plugin_meta['document'] = sprintf(
			'<a href="%s" target="_blank"><span class="dashicons dashicons-media-document"></span> مستندات</a>',
			esc_url( 'https://nabik.net/docs/gateland/' )
		);

		if ( ! defined( 'GATELAND_PRO_VERSION' ) ) {
			$plugin_meta[] = sprintf(
				'<a href="%s" target="_blank"><b><span class="dashicons dashicons-admin-network"></span> ارتقا به نسخه حرفه‌ای</b></a>',
				esc_url( 'https://l.nabik.net/gateland-pro/?utm_source=plugin_row_meta' )
			);
		}

		return $plugin_meta;
	}

}
