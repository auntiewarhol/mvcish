<?php
namespace awPHP\MVCish\Exception;

class NotFound extends \awPHP\MVCish\Exception {
	public function __construct($message=null,$code=null, \Exception $previous = null) {
		$this->statusText = parent::notfoundError;
		if (!isset($message)) $message = parent::notfoundError;
		if (!isset($code))    $code    = parent::NOTFOUND;
		parent::__construct($message, $code, $previous);
	}
}
?>
