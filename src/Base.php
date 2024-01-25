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
	//	public function code(int|E0E0\Parameter $value=new E0E0\Parameter()):?int {
	//		return $this->getSetScalar('code',$value);
	//	}
	//	protected array $data = [];
	//	public function data(null|string|array|E0E0\Parameter $key=null,mixed $value=new E0E0\Parameter(),bool $replace=true) {
	//		return $this->getSetHashArray('data',$key,$value,$replace);
	//	}
	//	protected array $list = [];
	//	public function list(mixed $value=null,bool $replace=true):?array {
	//		return $this->getSetListArray('list',$value,$replace);
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

 	// Hash = Associative Array. Yes, I'm from perl.
	protected function getSetHashArray(string $prop,null|string|array|E0E0\Parameter $key=new E0E0\Parameter(),mixed $set=new E0E0\Parameter(),bool $replace=true):mixed {

		if (!is_array($this->$prop))
			throw new Exception\ServerError("Cannot use getSetHashArray on ".gettype($this->$prop)." property $prop");

		// ->getSetHash($prop): no key sent, ignore other args, return the whole array
		if (isset($key) && $this->isDefaultedParam($key)) return $this->$prop ?? null;

		// ->getSetHash($prop,NULL): send key=null to clear the whole array
		if (!isset($key)) {
			$this->$prop = null;
		}
		// ->getSetHash($prop,[],null,?$replace): send key=array to replace or munge whole array
		// option bool arg $replace tells us whether to replace or merge|push, default replace.
		elseif (is_array($key)) {

			// of course it's always replace if currently empty
			if ($replace || !isset($this->$prop)) { $this->$prop = $key; }
			else if ($action = $this->chooseMergePush($prop,$this->$prop,$key)) {

				if      ($action == 'merge') { $this->$prop = array_merge($this->$prop,$key); }
				else if ($action == 'push')  { $this->$prop[] = $key; }
			}
		}
		else { // ->getSetHash($prop,'foo') $key is string

			if (!$this->isDefaultedParam($set)) { //if we actually got an arg

				// ->getSetHash($prop,'foo',$set), same as:
				// ->getSetHash($prop,'foo',$set,true): send set=anything to set the key
				// ->getSetHash($prop,'foo',NULL): send set=null to clear the key
				if ($replace || (!isset($set)) || (!isset($this->$prop[$key]))) {
					$this->$prop[$key] = $set;
				}
				else if ($action = $this->chooseMergePush($prop.'['.$key.']',$this->$prop[$key],$set)) {
					if ($action  == 'push')  {
						// "push"-ing one array onto another is just a merge, right?
						if (is_array($set)) { $action = 'merge'; }

						// else push it. push it real good.
						// ->getSetHash($prop,'foo',$scalarVal,'push')
						else                { $this->$prop[$key][] = $set; }
					}
					else if ($action  == 'merge') {
						$this->$prop[$key] = array_merge($this->$prop[$key],
							is_array($set) ? $set : [$set] //arrayify $set now if not already
						);
					}
				}
			}
			return $this->$prop[$key] ?? null;
		}
		return $this->$prop ?? null;
	}

	protected function getSetListArray(string $prop,mixed $value=new E0E0\Parameter(),bool $replace=true):mixed {
		// Array = zero-indexed simple array
		// ->getSetArray($prop) 		    // returns the whole $prop array
		// ->getSetArray($prop,'foo')	    // pushes $foo onto $prop
		// ->getSetArray($prop,['foo'])	    // replaces $prop with ['foo']
		// ->getSetArray($prop,NULL)	    // sets $prop to null
		// ->getSetArray($prop,$arr,false)  // pushes or merges $foo onto the $prop array, whatever $foo is. simple
										    // array will merge. assoc array pushes entire $foo as element

		// really just a convenience wrapper around the hash version to eliminate the extra parameter
		return $this->getSetHashArray($prop,$value,null,$replace);
	}


	private function chooseMergePush(string $propname,mixed &$prop,mixed &$value):string {

		if ((!empty($prop)) && !is_array($prop)) {
			throw new Exception\ServerError("Cannot merge or push onto non-array "
				.gettype($prop).' value stored in '.static::class.'->'.$propname);
		}
		// so $prop is an array, if $value is too, then choose
		if (is_array($value)) {

			// if $prop is a list (or empty)
			if ($this->isListArray($prop)) {
				// and $value is also a list, or $prop is empty [], then merge
				$action = ($this->isListArray($value) || empty($prop)) ? 'merge' :
					// but if $array is a hash then push
					'push';
			}
			// if $prop is a hash (by def, not empty), then merge
			else {
				$action == 'merge';
				 if ($this->isListArray($array)) {
					// but if $array is a list it's probably not what user intended so warn
					Exception\ServerWarning::throwWarning('Merged list-array onto hash aray stored in '
						.static::class.'->'.$propname.'; something may be wrong');
				}
			}
		}
		// if $value is anything else
		else if ($this->isListArray($prop)) {
			// we can push it onto a simple array
			$action = 'push';
		}
		else {
			// but if hash, we have to arrify value and merge. probably not what user intended
			$action = 'merge'; $value = [$value];
			Exception\ServerWarning::throwWarning("Merged non-array ".gettype($value)
					.' value onto hash array stored in '
					.static::class.'->'.$propname.'; something may be wrong');
		}
		return $action;
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
	public static function isHashArray(array $arr):bool {
        if ($arr === []) return false; // not yet at least
        return !self::isListArray($arr);
    }
	public static function isListArray(array $arr):bool {
        if ($arr === []) return true; // so far at least
        return array_keys($arr) === range(0, count($arr) - 1);
    }
}
?>
