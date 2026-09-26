<?php

namespace Nabik\Gateland\Gateways\Features;

use Exception;
use Nabik\Gateland\Models\Transaction;

interface RefundFeature {

	/**
	 * @param Transaction $transaction
	 * @param int         $amount
	 * @param string      $description
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function refund( Transaction $transaction, int $amount, string $description );

}