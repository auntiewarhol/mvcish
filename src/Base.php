<?php
namespace AuntieWarhol\MVCish;

abstract class Base {

	// Abstract Class to provide MVCish accessor to extension classes

	private $_MVCish;
	protected $_parentObject;

	public function __construct(\AuntieWarhol\MVCish\MVCish $MVCish) {
		$this->_MVCish = $MVCish;
	}
	function __destruct() {
		unset($this->_MVCish);
		unset($this->_parentObject);
	}
	public function MVCish(): \AuntieWarhol\MVCish\MVCish {
		return $this->_MVCish;
	}

	// enforce no-dynamic-properties
	public function __set($name, $value) {
		Exception\ServerWarning::throwWarning('Undefined property: '.static::class.'->'.$name);
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
		return get_parent_class($this) === 'AuntieWarhol\MVCish\Base';
	}
}
?>
