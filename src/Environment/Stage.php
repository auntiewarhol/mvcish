<?php
namespace AuntieWarhol\MVCish\Environment;
use \AuntieWarhol\MVCish\Util\HtmLawed;

class Stage extends \AuntieWarhol\MVCish\Environment\Production {

	public string $name = "Stage";
	public string $loggerLevel = [
		'DEFAULT' => 'Debug', 'CLI' => 'Debug'
	];
	public string $defaultAppConfigFilename = 'appConfig-Stage.php';
	public string $prettyPrintHTML = true;


	private $MVCish;
	public function __construct(\AuntieWarhol\MVCish\MVCish $MVCish) {
		$this->MVCish = $MVCish;

		// add TRACE to Exception messages without an errCode
		$this->messageBuilder($this->getNullCode(),
			function ($e,$basemsg):string {
				if ($msg = parent::buildExceptionMessage($e,$basemsg)) {
					return $msg . '; TRACE: '.$MVCish->getCallerInfo();
				}
			}
	}

	// add '(-$Env)' to outgoing email subject lines
	public function processEmailSubjectLine(string $subject):string {
		return $subject.' ('.$this->name().')';
	}

}
