<?php

namespace Nabik\Gateland\Services;

class CronService {

	public function __construct() {
		add_filter( 'cron_schedules', [ $this, 'cron_schedules' ], 1000 );
		add_action( 'wp', [ $this, 'check_status_scheduled' ] );
		add_action( 'gateland_cron_job', [ $this, 'automate_fix' ] );
	}

	public function cron_schedules( array $schedules ): array {

		$schedules['per_minute'] = [
			'interval' => MINUTE_IN_SECONDS,
			'display'  => 'هر دقیقه',
		];

		return $schedules;
	}

	public function check_status_scheduled() {
		if ( ! wp_next_scheduled( 'gateland_cron_job' ) ) {
			wp_schedule_event( time(), 'per_minute', 'gateland_cron_job' );
		}
	}

	public function automate_fix() {

		$error_transaction = TransactionService::incorrect_transactions( true )->first();

		if ( $error_transaction ) {

			try {
				TransactionService::fix( $error_transaction );
			} catch ( \Exception $e ) {
				error_log( $e->getMessage() );
			}

		}

	}
}