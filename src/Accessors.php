<?php
namespace awPHP\MVCish;
use \awPHP\MVCish\E0E0\Parameter;
use \awPHP\MVCish\Primitive\pArray;

trait Accessors {

	// we don't want magic method accessors, but you can use these to
	// do the repititve work in your accessors, eg:
	//
	//	protected int $code; // must be protected not private or Base can't see them
	//	public function code(int|E0E0\Parameter $value=new E0E0\Parameter()):?int {
	//		return $this->getSetScalar('code',$value);
	//	}
	//	protected array $data = [];
	//	public function data(null|string|array|E0E0\Parameter $key=null,
	//		mixed $value=new E0E0\Parameter(),bool $replace=true
	//	) {
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


	protected function getSetScalar(string $prop,mixed $value=new Parameter()):mixed {
		if (!$this->isDefaultedParam($value)) $this->$prop = $value;
		return $this->$prop ?? null;
	}

 	// Hash = Associative Array. Yes, I'm from perl.
	protected function getSetHashArray(string $prop,null|string|array|pArray|Parameter $key=new Parameter(),
		mixed $value=new Parameter(),bool $replace=true):mixed {

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
		elseif (is_countable($key)) { //countable = array|pArray

			// of course it's always replace if currently empty
			if ($replace || !isset($this->$prop)) {
				if (isset($key)) { $this->$prop = $key; }
				else             { unset($this->$prop); }
			}
			else if ($action = $this->chooseMergePush($prop,$this->$prop,$key)) {

				if      ($action == 'merge') { $this->$prop = pArray::array_merge($this->$prop,$key); }
				else if ($action == 'push')  { pArray::array_push($this->$prop,$key); }
			}
		}
		else { // ->getSetHash($prop,'foo') $key is string

			if (!$this->isDefaultedParam($value)) { //if we actually got an arg

				// ->getSetHash($prop,'foo',$value), same as:
				// ->getSetHash($prop,'foo',$value,true): send set=anything to set the key
				// ->getSetHash($prop,'foo',NULL): send set=null to clear the key
				if ($replace || (!isset($value)) || (!isset($this->$prop[$key]))) {
					$this->$prop[$key] = $value;
				}
				else if ($action = $this->chooseMergePush($prop.'['.$key.']',$this->$prop[$key],$value)) {
					if ($action  == 'merge') {
						$this->$prop[$key] = pArray::array_merge($this->$prop[$key],
							is_countable($value) ? $value : [$value] //arrayify $value now if not already
						);
					}
					elseif ($action  == 'push') { pArray::array_push($this->$prop[$key],$value); }
				}
			}
			return $this->$prop[$key] ?? null;
		}
		return $this->$prop ?? null;
	}

	protected function getSetListArray(string $prop,mixed $value=new Parameter(),bool $replace=true):mixed {
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

		if ((!empty($prop)) && !is_countable($prop)) {
			throw new Exception\ServerError("Cannot merge or push onto non-array "
				.gettype($prop).' value stored in '.static::class.'->'.$propname);
		}
		// so $prop is an array|pArray, if $value is too, then choose
		if (is_countable($value)) {

			// if $prop is a list (or empty)
			if ($this->isListArray($prop)) {
				// and $value is also a list, or $prop is empty [], then merge
				$action = ($this->isListArray($value) || empty($prop)) ? 'merge' :
					// but if $array is a hash then push
					'push';
			}
			// if $prop is a hash (by def, not empty), then merge
			else {
				$action = 'merge';
				 if ($this->isListArray($value)) {
					// but if $value is a list it's probably not what user intended so warn
					Exception\ServerWarning::throwWarning($this->MVCish(),
						'Merged list-array onto hash aray stored in '
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
			Exception\ServerWarning::throwWarning($this->MVCish(),
				"Merged non-array ".gettype($value)	.' value onto hash array stored in '
					.static::class.'->'.$propname.'; something may be wrong');
		}
		return $action;
	}

}
?>
