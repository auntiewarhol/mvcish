<?php
namespace awPHP\MVCish;

abstract class Primitive {

	public function __construct($value=null) {
		if (property_exists(static::class,'value')) {
			if (isset($value) || !isset($this->value)) $this->value = $value;
		}
	}
	public function __toString():string { strval($this->value); }

	public function value($value=null) {
		if (isset($value) || !isset($this->value)) $this->value = $value;
		if (property_exists(static::class,'value')) {
			return $this->value;
		}
	}
}
?>
