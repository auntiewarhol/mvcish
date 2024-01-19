<?php
namespace AuntieWarhol\MVCish\Environment;

class Local extends \AuntieWarhol\MVCish\Environment\Stage {

	public string $name = "Local";
	public string $defaultAppConfigFilename = 'appConfig-Local.php';


	// add TRACE to every defaulted-Exception message
	function buildDefaultExceptionMessage($e,$basemsg):string {
		if ($msg = parent::buildExceptionMessage($e,$basemsg)) {
			return $msg . '; TRACE: '.$MVCish->getCallerInfo();
		}
	}
}
