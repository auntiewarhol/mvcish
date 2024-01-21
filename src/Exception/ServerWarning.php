<?php
namespace AuntieWarhol\MVCish\Exception;
class ServerWarning extends \AuntieWarhol\MVCish\Exception {

	public static function throwWarning(string $message,string $file=null, int $line=null):void {
		$w = self::create($message,null,null,$file,$line);
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
		$GLOBALS['MVCish_handlingException'] = $this;
		trigger_error('E_MVCISH_WARNING: '.$this->getMessage(), E_USER_WARNING);
		$GLOBALS['MVCish_handlingException'] = null;
	}
}
?>
