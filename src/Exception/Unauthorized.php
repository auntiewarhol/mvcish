<?php
namespace awPHP\MVCish\Exception;

class Unauthorized extends \awPHP\MVCish\Exception {
	public function __construct($message=null,$code=null, \Exception $previous = null) {
		$this->statusText = parent::unauthorizedError;
		if (!isset($message)) $message = parent::unauthorizedError;
		if (!isset($code))    $code    = parent::UNAUTHORIZED;
		parent::__construct($message, $code, $previous);
	}
}
?>
