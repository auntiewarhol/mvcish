<?php
namespace AuntieWarhol\MVCish\Exception;

class NotFound extends \AuntieWarhol\MVCish\Exception {
	public function __construct($message=null,$code=null, \Exception $previous = null) {
		$this->statusText = parent::notfoundError;
		if (!isset($message)) $message = parent::notfoundError;
		if (!isset($code))    $code    = parent::NOTFOUND;
		parent::__construct($message, $code, $previous);
	}
}
?>
