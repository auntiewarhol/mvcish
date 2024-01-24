<?php
namespace awPHP\MVCish\Environment;

class Local extends \awPHP\MVCish\Environment\Stage {

	// add TRACE to every defaulted-Exception message
	function buildDefaultExceptionMessage($e,$basemsg):string {
		if ($msg = parent::buildDefaultExceptionMessage($e,$basemsg)) {
			return $msg . '; TRACE: '.\awPHP\MVCish\Debug::getTraceString(3,$e);
		}
	}
}
