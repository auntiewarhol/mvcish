<?php
namespace awPHP\MVCish\Primitive;
use E0E0\Parameter;

class pArray extends \awPHP\MVCish\Primitive implements \ArrayAccess,\Countable,\IteratorAggregate,\Serializable {

	protected array $value;

	public function __toString():string {
		return $this->listify(null) ?? '';
	}
	public function toArray():array {
		return $this->value;
	}

	public static function isHash(array $arr):bool {
        if ($arr === []) return false; // not yet at least
        return !self::isList($arr);
    }
	public static function isList(array $arr):bool {
        if ($arr === []) return true; // so far at least
        return array_keys($arr) === range(0, count($arr) - 1);
    }

	public function listify(string|null|Parameter $conjunction=new Parameter(), bool $oxford=true):string {
		$conjunction = is_a($conjunction,'awPHP\MVCish\E0E0\Parameter') ? ' and ' :
			(isset($conjunction) ? " $conjunction " : ' ');
		$a = $this->value() ?? [];
		$last = array_pop($a);
		$remaining = count($a);
		return ($remaining ? implode(', ',$a) .
			(($oxford && $remaining > 1) ? ',' : '') . $conjunction	: '') . $last;
	}


	// Countable
	public function count():int {
		return count($this->value());
	}

	// ArrayAccess
	public function offsetExists (mixed $offset):bool {
		return isset($this->value[$offset]);
	}
	public function offsetGet(mixed $offset):mixed {
		return isset($this->value[$offset]) ? $this->value[$offset] : null;
	}
	public function offsetSet(mixed $offset, mixed $value):void {
        if (is_null($offset)) {
            $this->value[] = $value;
        } else {
            $this->value[$offset] = $value;
        }
	}
	public function offsetUnset(mixed $offset):void	{
		unset($this->value[$offset]);
	}

	// IteratorAggregate
	public function getIterator(): \Traversable {
		return new \ArrayIterator($this->value);
	}

	// Serializable
	public function serialize(): ?string {
		return serialize(array($this->value));
	}
	public function unserialize(string $data): void {
		$this->value = unserialize($data);
	}

	// make callable like: array_function($this())
	public function &__invoke() { return $this->value; }
}
?>
