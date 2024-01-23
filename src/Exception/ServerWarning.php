<?php
namespace AuntieWarhol\MVCish\Exception;
class ServerWarning extends \AuntieWarhol\MVCish\Exception {

	public static function throwWarning(\AuntieWarhol\MVCish\MVCish $MVCish,string $message,string $file=null, int $line=null, array $trace=null):void {

		if (!isset($trace)) {
			$trace = debug_backtrace();
			//error_log("throwWarning fullTrace: ".\AuntieWarhol\MVCish\Debug::getTraceString(0,$trace));
			array_shift($trace); // pop this call
			$trace = \AuntieWarhol\MVCish\Debug::getFilteredTrace(0,$trace);
			//error_log("throwWarning filteredTrace: ".\AuntieWarhol\MVCish\Debug::getTraceString(0,$trace));
		}
		if (isset($trace[0]) && isset($trace[0]['file']) && !isset($file)) {
			$file = $trace[0]['file'];
			$line = $trace[0]['line'] ?? null;
			//error_log("throwWarning setting fileLine: $file ".(isset($line) ? $line : ''));
		}
		$w = self::create($message,null,null,$file,$line,E_USER_WARNING,$trace);
		$w->trigger($MVCish);
		//error_log("throwWarning fileLine now: ".$w->getFile().' '.$w->getLine());
	}

	public function __construct($message=null,$code=null, \Exception $previous = null) {
		$this->statusText = parent::warning;
		if (!isset($message)) $message = parent::warning;
		if (!isset($code))    $code    = parent::PHP_WARNING;
		parent::__construct($message, $code, $previous);
		$this->phpErrorCode(E_USER_WARNING);
	}

	public function trigger(\AuntieWarhol\MVCish\MVCish $MVCish) {
		//error_log("trigger global Exception, fileLine: ".$this->getFile().' '.$this->getLine());
		// call the error handler directly, so we can pass ourselves to it
		\AuntieWarhol\MVCish\Debug::errorHandler($MVCish,E_USER_WARNING,
			$this->getMessage(),$this->getFile(),$this->getLine(),$this);
		// now trigger php properly, tho our handler will ignore it the second time
		trigger_error('E_MVCISH_WARNING: '.$this->getMessage(), E_USER_WARNING);
	}
}
?>
