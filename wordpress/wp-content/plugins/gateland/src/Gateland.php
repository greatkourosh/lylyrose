<?php
/**
 * Developer : MahdiY
 * Web Site  : MahdiY.IR
 * E-Mail    : M@hdiY.IR
 * اَلسَّلامُ عَلَى الْحُسَيْنِ
 * وَ عَلى عَلِىِّ بْنِ الْحُسَيْنِ
 * وَ عَلى اَوْلادِ الْحُسَيْنِ
 * وَ عَلى اَصْحابِ الْحُسَيْنِ
 */

namespace Nabik\Gateland;

use Nabik\Gateland\Admin\Menu;
use Nabik\Gateland\Services\APIService;
use Nabik\Gateland\Services\CronService;
use Nabik\Gateland\Services\SMSService;

defined( 'ABSPATH' ) || exit;

class Gateland {

	protected static ?Gateland $_instance = null;

	public static function instance(): ?Gateland {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	private function __construct() {
		$this->autoload();
		$this->init_hooks();
	}

	private function autoload() {
		new Menu();

		new SMSService();
		new APIService();
		new CronService();
	}

	private function init_hooks() {
		add_action( 'init', [ $this, 'addRewriteRules' ] );
		add_filter( 'query_vars', [ $this, 'addQueryVars' ] );
		add_action( 'wp', [ $this, 'handleCustomRule' ] );

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_alpine' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_alpine' ] );
		add_action( 'login_enqueue_scripts', [ $this, 'enqueue_alpine' ] );
	}

	public static function addRewriteRules() {
		$prefix = self::get_option( 'sms.pay_link', 'pay' );

		$regex = sprintf( '^%s/([^/]+)/?', $prefix );
		add_rewrite_rule( $regex, 'index.php?gateland_page=pay&gateland_id=$matches[1]', 'top' );
	}

	public static function addQueryVars( $vars ) {
		$vars[] = 'gateland_page';
		$vars[] = 'gateland_id';

		return $vars;
	}

	public static function handleCustomRule() {
		global $wp_query;

		$transaction_token = get_query_var( 'gateland_id' );
		$page              = get_query_var( 'gateland_page' );

		if ( $page == 'pay' && is_string( $transaction_token ) ) {

			$wp_query->is_404 = false;
			Pay::pay( $transaction_token );

			exit();
		}

	}

	public function enqueue_alpine() {
		$suffix = wp_scripts_get_suffix();
		wp_register_script( 'alpine', GATELAND_URL . 'assets/js/alpine' . $suffix . '.js', [], '3.15.0', [ 'strategy' => 'defer' ] );
	}

	public static function get_option( string $option_name, $default = null ) {

		[ $section, $option ] = explode( '.', $option_name );

		$options = get_option( 'gateland_' . $section, [] );

		if ( isset( $options[ $option ] ) ) {
			return $options[ $option ];
		}

		return $default;
	}

	public static function set_option( string $option_name, $value ) {

		[ $section, $option ] = explode( '.', $option_name );

		$options = get_option( 'gateland_' . $section, [] );
		$options = empty( $options ) ? [] : $options;

		$options[ $option ] = $value;

		update_option( 'gateland_' . $section, $options );
	}

	/**
	 * @param string $url
	 *
	 * @return never-return
	 */
	public static function redirect( string $url ) {
		wp_redirect( $url );
		exit;
	}

	public static function log( ...$params ) {
		$log = '';

		$date = wp_date( 'Y-m-d' );

		if ( defined( 'WC_LOG_DIR' ) ) {
			$log_dir = WC_LOG_DIR;
		} else {
			$upload_dir = wp_upload_dir();
			$log_dir    = $upload_dir['basedir'] . '/wc-logs/';
		}

		$log_file = $log_dir . "gateland-{$date}.log";

		foreach ( $params as $message ) {

			$log .= wp_date( '[Y-m-d H:i:s] ' );

			if ( is_array( $message ) || is_object( $message ) ) {
				$log .= print_r( $message, true );
			} elseif ( is_bool( $message ) ) {
				$log .= ( $message ? 'true' : 'false' );
			} else {
				$log .= $message;
			}

			$log .= PHP_EOL;
		}

		file_put_contents( $log_file, $log, FILE_APPEND );
	}
}
