<?php

namespace Nabik\Gateland\Gateways\Features;

use Exception;
use Nabik\Gateland\Models\Transaction;

interface InquiryFeature {

	/**
	 * @param Transaction $transaction
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function inquiry( Transaction $transaction ): bool;

}