<?php
namespace awPHP\MVCish;

abstract class Base {

	// Abstract Class to provide MVCish accessor to extension classes

	private $_MVCish;
	protected $_parentObject;

	public function __construct(\awPHP\MVCish\MVCish $MVCish) {
		$this->_MVCish = $MVCish;
	}
	function __destruct() {
		unset($this->_MVCish);
		unset($this->_parentObject);
	}
	public function MVCish(): \awPHP\MVCish\MVCish {
		return $this->_MVCish;
	}

	// enforce no-dynamic-properties
	public function __set($name, $value) {
		Exception\ServerWarning::throwWarning($this->MVCish(),'Attempt to set undefined property: '
			.static::class.'->'.$name);
	}
	public function __get($name) {
		Exception\ServerWarning::throwWarning($this->MVCish(),'Attempt to read undefined property: '
			.static::class.'->'.$name);
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


	// we don't want magic method accessors, but you can use these to
	// do the repititve work in your accessors, eg:
	//
	//	protected int $code; // must be protected not private or Base can't see them
	//	public function code(int $set=null):?int {
	//		return $this->getSetScalar('code',$set);
	//	}
	//	protected array $data = [];
	//	public function data(string $key=null,$set=null,$setAll=null) {
	//		return $this->getSetArray('data',$key,$set,$setAll);
	//	}
	//
	// will auto warn/err if you haven't defined $prop
	// we don't check type on Scalar, so you should.

	protected static function isDefaultedParam($arg) {
		return is_a($arg,'awPHP\MVCish\E0E0\Parameter');
	}

	protected function getSetScalar(string $prop,mixed $set=new E0E0\Parameter()):mixed {
		if (!$this->isDefaultedParam($set)) $this->$prop = $set;
		return $this->$prop ?? null;
	}
	protected function getSetArray(string $prop,null|string|array|E0E0\Parameter $key=new E0E0\Parameter(),mixed $set=new E0E0\Parameter(),string $action=null):mixed {

		// ->getSetArray($prop): no key sent, ignore other args, return the whole array
		if (isset($key) && $this->isDefaultedParam($key)) return $this->$prop ?? null;

		$action ??= 'replace';
		// ->getSetArray($prop,NULL): send key=null to clear the whole array
		if (!isset($key)) {
			$this->$prop = null;
		}
		// ->getSetArray($prop,[],?$action): send key=array to replace or munge whole array
		// option arg $action tells us to replace|merge|push
		elseif (is_array($key)) {
			$this->$prop ??= [];
			if     ($action == 'replace') { $this->$prop = $key; }
			elseif ($action == 'merge')   { $this->$prop = array_merge($this->$prop,$key); }
		}
		else { // ->getSetArray($prop,'foo') $key is string

			if (!$this->isDefaultedParam($set)) { //if we actually got an arg

				// ->getSetArray($prop,'foo',NULL): send set=null to clear the key
				if (!isset($set)) $action = 'replace'; // NULL always replaces, action ignored

				// ->getSetArray($prop,'foo',$set), same as:
				// ->getSetArray($prop,'foo',$set,'replace'): send set=anything to set the key
				if ($action == 'replace') { $this->$prop[$key] = $set; }

				else {
					// array-ify current if not already
					if (!is_array($this->$prop[$key])) $this->$prop[$key] = [$this->$prop[$key]];

					if ($action  == 'push')  {
						// "push"-ing one array onto another is just a merge, right?
						if (is_array($set)) { $action = 'merge'; }

						// else push it. push it real good.
						// ->getSetArray($prop,'foo',$scalarVal,'push')
						else                { $this->$prop[$key][] = $set; }
					}
					if ($action  == 'merge') {
						if (!is_array($set)) { $set = [$set]; } //arrayify $set now if not already
						// ->getSetArray($prop,'foo',$arrayVal,'merge'): send set=anything to set the key
						$this->$prop[$key] = array_merge($this->$prop[$key],$set);
					}
				}
			}
			return $this->$prop[$key] ?? null;
		}
		return $this->$prop ?? null;
	}
	protected function getPushArray(string $prop,$set=null,array $setAll=null,array $opts=null):?array {
		if (isset($setAll)) {
			$this->$prop = $setAll;
		}
		else if (isset($set)) {
			$this->$prop[] = $set;
		}
		return $this->$prop ?? null;
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
}
?>
