<?php
namespace awPHP\MVCish\Util;

# assumes we've installed vanilla/htmlawed;
# provides the class wrapper they didn't.

class HtmLawed {

	public static function call($meth,$argArray) {
		require_once dirname(__FILE__).'/../../../../vanilla/htmlawed/src/htmLawed/htmLawed.php';
		return call_user_func_array($meth,$argArray);
	}
}
