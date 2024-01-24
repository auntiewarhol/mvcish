<?php
namespace awPHP\MVCish\Exception;

class NotAllowed extends \awPHP\MVCish\Exception {
	public function __construct($message=null,$code=null, \Exception $previous = null) {
		$this->statusText = parent::notAllowedError;
		if (!isset($message)) $message = parent::notAllowedError;
		if (!isset($code))    $code    = parent::NOT_ALLOWED;
		parent::__construct($message, $code, $previous);
	}
}
?>
