<?php
namespace awPHP\MVCish\Primitive\pArray;

class pHash extends \awPHP\MVCish\Primitive\pArray {
	public function __construct(array $value=null) {
		// it's fine if $value was a List, but it won't be considered one now.
		parent::__construct($value);
	}
}
?>
