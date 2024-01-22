<?php
namespace AuntieWarhol\MVCish\Exception;
class ServerWarning extends \AuntieWarhol\MVCish\Exception {

	public static function throwWarning(string $message,string $file=null, int $line=null, array $trace=null):void {
		$trace ??= \AuntieWarhol\MVCish\Debug::getFilteredTrace();
		if (isset($trace[0]) && isset($trace[0]['file']) && !isset($file)) {
			$file = $trace[0]['file'];
			$line = $trace[0]['line'] ?? null;
		}
		$w = self::create($message,null,null,$file,$line,E_USER_WARNING,$trace);
		$w->trigger();
	}

	public function __construct($message=null,$code=null, \Exception $previous = null) {
		$this->statusText = parent::warning;
		if (!isset($message)) $message = parent::warning;
		if (!isset($code))    $code    = parent::PHP_WARNING;
		parent::__construct($message, $code, $previous);
		$this->phpErrorCode(E_USER_WARNING);
	}

	public function trigger() {
		//hacky, but...
		$GLOBALS['MVCish_handlingException'] = $this;
		trigger_error('E_MVCISH_WARNING: '.$this->getMessage(), E_USER_WARNING);
		$GLOBALS['MVCish_handlingException'] = null;
	}
}
?>
