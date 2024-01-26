<?php
namespace awPHP\MVCish;

trait NoDynamic {

	// enforce no-dynamic-properties
	public function __set($name, $value) {
		Exception\ServerWarning::triggerWarning('Attempt to set undefined property: '.static::class.'->'.$name);
	}
	public function __get($name) {
		Exception\ServerWarning::triggerWarning('Attempt to read undefined property: '.static::class.'->'.$name);
	}
}
?>
