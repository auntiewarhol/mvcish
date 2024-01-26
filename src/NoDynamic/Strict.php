<?php
namespace awPHP\MVCish\NoDynamic;

trait Strict {

	// enforce no-dynamic-properties
	public function __set($name, $value) {
		throw new Exception\ServerError('Attempt to set undefined property: '.static::class.'->'.$name);
	}
	public function __get($name) {
		throw new Exception\ServerError('Attempt to read undefined property: '.static::class.'->'.$name);
	}
}
?>
