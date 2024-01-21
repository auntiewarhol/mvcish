<?php
namespace AuntieWarhol\MVCish\Environment;

class Local extends \AuntieWarhol\MVCish\Environment\Stage {

	// add TRACE to every defaulted-Exception message
	function buildDefaultExceptionMessage($e,$basemsg):string {
		if ($msg = parent::buildDefaultExceptionMessage($e,$basemsg)) {
			return $msg . '; TRACE: '.$this->MVCish()->getCallerInfo(3);
		}
	}
}
