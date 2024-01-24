<?php
namespace awPHP\MVCish\Exception;

class Forbidden extends \awPHP\MVCish\Exception {
	public function __construct($message=null,$code=null, \Exception $previous = null) {
		$this->statusText = parent::forbiddenError;
		if (!isset($message)) $message = parent::forbiddenError;
		if (!isset($code))    $code    = parent::FORBIDDEN;
		parent::__construct($message, $code, $previous);
	}
}
?>
