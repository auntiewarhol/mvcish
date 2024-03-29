<?php
namespace awPHP\MVCish\Exception;
class ServerWarning extends \awPHP\MVCish\Exception {

	public static function throwWarning(\awPHP\MVCish\MVCish $MVCish,string $message,string $file=null, int $line=null, array $trace=null):void {
		self::triggerWarning($message,$file,$line,$trace,$MVCish);
	}

	public static function triggerWarning(string $message,string $file=null, int $line=null, array $trace=null,\awPHP\MVCish\MVCish $MVCish=null):void {
		if (!isset($trace)) {
			$trace = debug_backtrace();
			//error_log("triggerWarning fullTrace: ".\awPHP\MVCish\Debug::getTraceString(0,$trace));
			array_shift($trace); // pop this call
			$trace = \awPHP\MVCish\Debug::getFilteredTrace(0,$trace);
			//error_log("triggerWarning filteredTrace: ".\awPHP\MVCish\Debug::getTraceString(0,$trace));
		}
		if (isset($trace[0]) && isset($trace[0]['file']) && !isset($file)) {
			$file = $trace[0]['file'];
			$line = $trace[0]['line'] ?? null;
			//error_log("triggerWarning setting fileLine: $file ".(isset($line) ? $line : ''));
		}
		$w = self::create($message,null,null,$file,$line,E_USER_WARNING,$trace);
		$w->trigger($MVCish);
	}


	public function __construct($message=null,$code=null, \Exception $previous = null) {
		$this->statusText = parent::warning;
		if (!isset($message)) $message = parent::warning;
		if (!isset($code))    $code    = parent::PHP_WARNING;
		parent::__construct($message, $code, $previous);
		$this->phpErrorCode(E_USER_WARNING);
	}

	public function trigger(\awPHP\MVCish\MVCish $MVCish=null) {
		$prefix = '';
		if (isset($MVCish)) {
			//error_log("trigger global Exception, fileLine: ".$this->getFile().' '.$this->getLine());
			// call the error handler directly, so we can pass ourselves to it
			\awPHP\MVCish\Debug::errorHandler($MVCish,E_USER_WARNING,
				$this->getMessage(),$this->getFile(),$this->getLine(),$this);
			$prefix = $this->getWarningPrefix();
			// now trigger php properly, tho our handler will ignore it the second time
		} // otherwise...
		trigger_error($prefix.$this->getMessage(), E_USER_WARNING);
	}
}
?>
