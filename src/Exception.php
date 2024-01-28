<?php
namespace awPHP\MVCish;
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
		string $file = null, int $line = null, int $errno = null, array $trace = null) {

		$e = new static($message,$code,$previous);
		$e->phpErrorCode($errno);

		// if passed a trace, use it to override file and line		
		if (isset($trace)) {
			//error_log("createException usingTrace: ".\awPHP\MVCish\Debug::getTraceString(0,$trace));
			$e->overrideFileLineTrace(null,null,$trace);
		}
		// otherwise get and stash a trace, but use file/line that were passed
		else {
			$trace = debug_backtrace(); array_shift($trace);
			//error_log("createException gotTrace: ".\awPHP\MVCish\Debug::getTraceString(0,$trace));
			$e->overrideFileLineTrace($file,$line);
			$e->getOverrideTrace($trace);
		}
		return $e;
	}
	public function __construct($message=null,$code=null, \Exception $previous = null) {
		parent::__construct($message, $code, $previous);
		if (!isset($this->overrideTrace)) {
			$trace = debug_backtrace(); array_shift($trace);
			$this->getOverrideTrace($trace);
		}
	}
	//**********************************************************************************

	// unfortunately 'trace' on the parent object is private, unlike file and line,
	// so we can't easily override it. Just keep track of our own versions and try
	// to use them instead when needed.

	private array $overrideTrace;
	private array $filteredTrace;
	public function getFilteredTrace():array {
		if (!isset($this->filteredTrace)) {
			$this->filteredTrace = Debug::getFilteredTrace(0,$this);
		}
		return $this->filteredTrace;
	}
	public function getOverrideTrace(array $set=null):array {
		if (isset($set)) {
			$this->overrideTrace = $set;
			unset($this->filteredTrace);
		}
		return $this->overrideTrace ?? $this->getTrace();
	}
	public function overrideFileLineTrace(string $file=null,$line=null,$trace=null):void {
		//error_log("createException overide received Trace: ".\awPHP\MVCish\Debug::getTraceString(0,$trace));
		$fileSet = false;
		if (isset($trace)) {
			$trace = Debug::getFilteredTrace(0,$trace);
			foreach($trace as $t) {
				if (isset($t['file'])) {
					//error_log("createException overide file line: ".$t['file'].' '.$t['line']);
					$this->file = $t['file'];
					$this->line = $t['line'];
					$fileSet = true;
					break;
				}
			}
			$this->getOverrideTrace($trace);
		}
		if (isset($file) && !$fileSet) {
			$this->file = $file;
			$this->line = isset($line) ? $line : null;
		}
		//error_log("filtered trace2=".Debug::getTraceString(0,$this->getFilteredTrace()));
	}


	// translate php errors and warnings into exception objects
	public static function handlerFactory($MVCish, $errno, $errstr, $errfile, $errline, array $trace):self {

		$errstr = self::cleanWarningPrefix($errno,$errstr);
		$e = Debug::isFatalPHPerrCode($errno) ?
			Exception\ServerError::create($errstr,null,null,$errfile,$errline,$errno,$trace):
			Exception\ServerWarning::create($errstr,null,null,$errfile,$errline,$errno,$trace);

		$e->phpErrorCode($errno);
		$e->overrideFileLineTrace();
		return $e;
	}

	private const WARNING_PREFIX ='E_MVCISH_WARNING: ';
	public static function getWarningPrefix() { return self::WARNING_PREFIX; }

	public static function cleanWarningPrefix(int $errno, string $errstr,bool &$wasCleaned=false):string {
		if (($errno == E_USER_WARNING) && (substr($errstr,0,18) == self::WARNING_PREFIX)) {
			$wasCleaned = true;
			return substr($errstr,strlen(self::WARNING_PREFIX));
		}
		return $errstr;
	}
	public static function isWarningPrefixed(string $str):bool {
		return str_starts_with($str, self::WARNING_PREFIX);
	}
}

?>
