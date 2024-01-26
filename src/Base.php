<?php
namespace awPHP\MVCish;
use \awPHP\MVCish\MVCish;
use \awPHP\MVCish\MVCish\Primitive\pArray;

abstract class Base {
	use NoDynamic;
	use Accessors;

	// Abstract Class to provide MVCish accessor and utils to extension classes

	private $_MVCish;
	protected $_parentObject;

	public function __construct(MVCish $MVCish) {
		$this->_MVCish = $MVCish;
	}
	function __destruct() {
		unset($this->_MVCish);
		unset($this->_parentObject);
	}
	public function MVCish(): MVCish {
		return $this->_MVCish;
	}


	public function parentObject(self $new = null):?self {

		if (isset($new)) $this->_parentObject = $new;

		if (!isset($this->_parentObject)) {
			
			// By default we will auto-instanstiate NEW objects
			// of this object's parent class, so long as that 
			// parent is not Base itself, which can be useful particularly
			// in some recursive situations. This is not "the parent
			// who created me", though a subclass could choose to
			// use it that way for its children, by creating children
			// and then using the setter to bypass this code, eg:
			// 
			// # where $this = an object of a class that extends from Base
			// $child = new ThisClass\ChildClass($this->MVCish());
			// $child->parentObject($this);
			//
			// $this (and $child) will both have inherited our destructor
			// to enusre object is properly garbage collected

			if (($parentClasses = array_slice(class_parents($this),0,-1)) &&
				($parentClass = array_shift($parentClasses))
			) {
				$this->_parentObject = new $parentClass($this->MVCish());
			}
		}
		return $this->_parentObject;
	}

	// tells whether the current object is directly extended from Base
	// (as opposed to a subclass of such a class)
	protected function isRootClass():bool {
		return get_parent_class($this) === 'awPHP\MVCish\Base';
	}


	//****************************************************************************
	// Misc utils & conveniences

	public static function parseBool(string $string,bool &$boolVal=null) {
		$boolVal = filter_var($string,FILTER_VALIDATE_BOOLEAN,FILTER_NULL_ON_FAILURE);
		return isset($boolVal);
	}

	public static function listifyArray(array $array,string $conjunction='and',bool $oxford=true):string {
		$last = array_pop($array);
		$remaining = count($array);
		return ($remaining ?
				implode(', ',$array) . (($oxford && $remaining > 1) ? ',' : '') . " $conjunction "
			: '') . $last;
	}

	// until we can use the 8.1 built-in
	public static function isHashArray(array|pArray $arr):bool {
		if (is_a($arr,pArray)) return $arr->isHash();
        if ($arr === []) return false; // not yet at least
        return !self::isListArray($arr);
    }
	public static function isListArray(array|pArray $arr):bool {
		if (is_a($arr,pArray)) return $arr->isList();
        if ($arr === []) return true; // so far at least
        return array_keys($arr) === range(0, count($arr) - 1);
    }
}
?>
