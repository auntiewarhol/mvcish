<?php
namespace awPHP\MVCish\Exception;

class MovedPermanently extends \awPHP\MVCish\Exception {

	public $internalRedirect  = false;

	// note redirect url instead of message in constructor, and is required
	public function __construct($redirectUrl,$code=null, \Exception $previous = null) {
		$this->statusText = parent::movedPermanently;
		$message = parent::movedPermanently;
		if (!isset($code)) $code = parent::MOVED_PERMANENTLY;
		parent::__construct($message, $code, $previous);
		$this->setRedirectUrl($redirectUrl);
	}
}
?>
