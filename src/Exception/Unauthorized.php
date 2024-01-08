<?php
namespace AuntieWarhol\MVCish\Exception;

class Unauthorized extends \AuntieWarhol\MVCish\Exception {
	public function __construct($message=null,$code=null, \Exception $previous = null) {
		$this->statusText = parent::unauthorizedError;
		if (!isset($message)) $message = parent::unauthorizedError;
		if (!isset($code))    $code    = parent::UNAUTHORIZED;
		parent::__construct($message, $code, $previous);
	}
}
?>
