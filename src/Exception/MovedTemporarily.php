<?php
namespace AuntieWarhol\MVCish\Exception;

class MovedTemporarily extends \AuntieWarhol\MVCish\Exception {

	public $internalRedirect  = false;

	// note redirect url instead of message in constructor, and is required
	public function __construct($redirectUrl,$code=null, \Exception $previous = null) {
		$this->statusText = parent::movedTemporarily;
		$message = parent::movedTemporarily;
		if (!isset($code)) $code = parent::MOVED_TEMPORARILY;
		parent::__construct($message, $code, $previous);
		$this->setRedirectUrl($redirectUrl);
	}
}
?>
