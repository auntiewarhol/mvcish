<?php
namespace AuntieWarhol\MVCish\Environment;
use \AuntieWarhol\MVCish\Util\HtmLawed;

class Stage extends \AuntieWarhol\MVCish\Environment\Production {

	private array $loggerLevel = [
		'DEFAULT' => 'Debug', 'CLI' => 'Debug'
	];
	private bool $prettyPrintHTML = true;

	public function __construct(\AuntieWarhol\MVCish\MVCish $MVCish) {
		parent::__construct($MVCish);
		// add TRACE to Exception messages without an errCode
		$this->messageBuilder($this->getNullCode(),
			function ($e,$basemsg):string {
				if ($msg = parent::buildExceptionMessage($e,$basemsg)) {
					return $msg . '; TRACE: '.$MVCish->getCallerInfo(3,$e);
				}
			}
		);
	}

	// add '(-$Env)' to outgoing email subject lines
	public function processEmailSubjectLine(string $subject):string {
		return $subject.' ('.$this->name().')';
	}

}
