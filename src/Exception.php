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

	public static function create($message=null,$code=null, \Exception $previous = null,
		string $file = null, int $line = null) {
		$e = new static($message,$code,$previous);
		$e->overrideFileLine($file,$line);
		return $e;
	}
	
	public function __construct($message=null,$code=null, \Exception $previous = null) {
		parent::__construct($message, $code, $previous);
		// override File and Line with better info (tries to eliminate 
		// "thrown from the place that throws everything"-junk)
		if ($trace = $this->getFilteredTrace(1)) {
			if (isset($trace[0]['file'])) {
				$this->overrideFileLine($trace[0]['file'],$trace[0]['line']);
			}
		}

	}
	private array $trace;
	public function getFilteredTrace(int $max=0,bool $reset=false):array {
		if ($reset || !isset($this->trace)) {
			$this->trace = \AuntieWarhol\MVCish\MVCish::getRelevantCallers($max,$this);
		}
		return $this->trace;
	}
	public function overrideFileLine(string $file=null,$line=null):bool {
		if (isset($file)) {
			$this->file = $file;
			$this->line = isset($line) ? $line : null;
			$this->getFilteredTrace(0,true);
			return true;
		}
		return false;
	}


	// translate php errors and warnings into exception objects
	public static function handlerFactory($MVCish, $errno, $errstr, $errfile, $errline) {

		$errstr = \AuntieWarhol\MVCish\MVCish::cleanMVCishWarning($errno,$errstr);

		$e = $MVCish->isFatalPHPerrCode($errno) ?
			\AuntieWarhol\MVCish\Exception\ServerError::create($errstr,$errno,null,$errfile,$errline):
			\AuntieWarhol\MVCish\Exception\ServerWarning::create($errstr,$errno,null,$errfile,$errline);

		$e->phpErrorCode($errno);
		return $e;
	}
}

?>
