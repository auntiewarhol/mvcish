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

	// not typically used for server responses, but 
	// may be used when logging php warnings/notices
	const PHP_WARNING       = 199; //repurposing deprecated status code
	const warning           = 'Warning';

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

	protected int $phpErrorCode;
	public function phpErrorCode($set=null):?int {
		if (isset($set)) $this->phpErrorCode = $set;
		return $this->phpErrorCode ?? null;
	}


	// override File and Line if Trace gives better info

	//**********************************************************************************

	// meta-constructor which wraps the actual constructor, allowing us to optionally
	// pass in and use extra parameters
	public static function create($message=null,$code=null, \Exception $previous = null,
		string $file = null, int $line = null, int $errno = null) {
		$e = new static($message,$code,$previous);
		$e->phpErrorCode($errno);
		$e->overrideFileLineTrace($file,$line);
		return $e;
	}
	//public function __construct($message=null,$code=null, \Exception $previous = null) {
	//	parent::__construct($message, $code, $previous);
	//	error_log("original trace=".\AuntieWarhol\MVCish\MVCish::getCallerInfo(0,$this->getTrace()));
	//	error_log("filtered trace1=".\AuntieWarhol\MVCish\MVCish::getCallerInfo(0,$this->getFilteredTrace()));
	//}
	//**********************************************************************************

	private array $filteredTrace;
	public function getFilteredTrace(bool $reset=false):array {
		if ($reset || !isset($this->filteredTrace)) {
			$this->filteredTrace = \AuntieWarhol\MVCish\MVCish::getRelevantCallers(0,$this);
		}
		return $this->filteredTrace;
	}
	public function overrideFileLineTrace(string $file=null,$line=null):void {
		if (isset($file)) {
			$this->file = $file;
			$this->line = isset($line) ? $line : null;
			$this->getFilteredTrace(true);
		}
		elseif ($trace = $this->getFilteredTrace(true)) {
			if (isset($trace[0]) && isset($trace[0]['file'])) {
				$this->file = $trace[0]['file'];
				$this->line = $trace[0]['line'];
			}
		}
		//error_log("filtered trace2=".\AuntieWarhol\MVCish\MVCish::getCallerInfo(0,$this->getFilteredTrace()));
	}


	// translate php errors and warnings into exception objects
	public static function handlerFactory($MVCish, $errno, $errstr, $errfile, $errline) {

		$errstr = \AuntieWarhol\MVCish\MVCish::cleanMVCishWarning($errno,$errstr);

		$e = $MVCish->isFatalPHPerrCode($errno) ?
			\AuntieWarhol\MVCish\Exception\ServerError::create($errstr,null,null,$errfile,$errline,$errno):
			\AuntieWarhol\MVCish\Exception\ServerWarning::create($errstr,null,null,$errfile,$errline,$errno);

		$e->phpErrorCode($errno);
		return $e;
	}
}

?>
