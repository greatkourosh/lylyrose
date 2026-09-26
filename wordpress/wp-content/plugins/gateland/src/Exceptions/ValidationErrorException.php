<?php

namespace Nabik\Gateland\Exceptions;

use Rakit\Validation\ErrorBag;
use Throwable;

defined( 'ABSPATH' ) || exit;

final class ValidationErrorException extends \Exception {

	private $errors;

	public function __construct( $errors, $message = "", $code = 0, Throwable $previous = null ) {

		$this->errors = $errors;

		parent::__construct( $message, $code, $previous );
	}

	/**
	 * @return ErrorBag
	 */
	public function getErrors() {
		return $this->errors;
	}
}