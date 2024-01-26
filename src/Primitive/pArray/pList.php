<?php
namespace awPHP\MVCish\Primitive\pArray;

class pList extends \awPHP\MVCish\Primitive\pArray {
	public function __construct(array $value=null) {
		if (!self::isList($value))
			throw new \awPHP\Exception\ServerError("Cannot construct "
				.static::class." object with an Associative Array");
		parent::__construct($value);
	}
}
?>
