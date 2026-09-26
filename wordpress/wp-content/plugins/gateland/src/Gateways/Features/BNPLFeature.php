<?php

namespace Nabik\Gateland\Gateways\Features;

use Exception;
use Nabik\Gateland\Models\Transaction;

interface BNPLFeature {
	/**
	 * @param Transaction $transaction
	 *
	 * @return void
	 *
	 * @throws Exception
	 */
	public function request( Transaction $transaction ): void;

	public function redirect( Transaction $transaction );
}