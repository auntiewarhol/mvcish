<?php
namespace awPHP\MVCish\Exception;

class ServerError extends \awPHP\MVCish\Exception {
	public function __construct($message=null,$code=null, \Exception $previous = null) {
		$this->statusText = parent::serverError;
		if (!isset($message)) $message = parent::serverError;
		if (!isset($code))    $code    = parent::SERVER_ERROR;
		parent::__construct($message, $code, $previous);
	}
}
?>
