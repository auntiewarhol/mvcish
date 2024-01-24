<?php
namespace awPHP\MVCish\Environment;
use \awPHP\MVCish\Util\HtmLawed;

class Stage extends \awPHP\MVCish\Environment\Production {

	private array $loggerLevel = [
		'DEFAULT' => 'Debug', 'CLI' => 'Debug'
	];
	private bool $prettyPrintHTML = true;

	public function __construct(\awPHP\MVCish\MVCish $MVCish) {
		parent::__construct($MVCish);
		// add TRACE to Exception messages without an errCode
		$this->messageBuilder($this->getNullCode(),
			function ($e,$basemsg):string {
				if ($msg = parent::buildExceptionMessage($e,$basemsg)) {
					return $msg . '; TRACE: '.\awPHP\MVCish\Debug::getTraceString(3,$e);
				}
			}
		);
	}

	// add '(-$Env)' to outgoing email subject lines
	public function processEmailSubjectLine(string $subject):string {
		return $subject.' ('.$this->name().')';
	}

}
