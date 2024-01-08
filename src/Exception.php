<?php
namespace AuntieWarhol\MVCish;
class Exception extends \Exception {

	const SERVER_ERROR      = 500;
	const UNAUTHORIZED      = 401;
	const FORBIDDEN         = 403;
	const NOTFOUND          = 404;
	const NOT_ALLOWED       = 405;
	const MOVED_PERMANENTLY = 301;
	const MOVED_TEMPORARILY = 302;
	const SEE_OTHER         = 303;

	const serverError       = 'Internal Server Error';
	const unauthorizedError = 'Unauthorized';
	const forbiddenError    = 'Forbidden';
	const notfoundError     = 'Not Found';
	const notAllowedError   = 'Method Not Allowed';
	const movedPermanently  = 'Moved Permanently';
	const movedTemporarily  = 'Moved Temporarily';
	const seeOther          = 'See Other';

	// generally a redirect-on-error should be handled internally
	// by forwarding to the new controller. 3XX errors will 
	// override this to do a real server redirect.
	// internal redirect would most commonly be used to
	// redirect to login on an Unauthorized error
	public $internalRedirect  = true;

	private $redirectUrl;
	public function setRedirectUrl($url) {
		$this->redirectUrl = $url;
	}
	public function getRedirectUrl() {
		return $this->redirectUrl;
	}
	public function isInternalRedirect($set=null) {
		if (isset($set)) $this->internalRedirect = $set;
		return $this->internalRedirect;
	}

	protected $statusText;
	public function statusText() {
		return $this->statusText;
	}
}

?>
