<?php
namespace awPHP\MVCish\Exception;

class SeeOther extends \awPHP\MVCish\Exception {

	public $internalRedirect  = false;

	// note redirect url instead of message in constructor, and is required
	public function __construct($redirectUrl,$code=null, \Exception $previous = null) {
		$this->statusText = parent::seeOther;
		$message = parent::seeOther;
		if (!isset($code)) $code = parent::SEE_OTHER;
		parent::__construct($message, $code, $previous);
		$this->setRedirectUrl($redirectUrl);
	}
}
?>
