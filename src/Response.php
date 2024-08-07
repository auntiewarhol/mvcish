<?php
namespace awPHP\MVCish;
use \awPHP\MVCish\MVCish;
#use \awPHP\MVCish\E0E0\Parameter;

class Response extends Base implements \ArrayAccess,\Countable,\IteratorAggregate,\Serializable ,\JsonSerializable {

	// goofy hack until php 8.1
	const dParam = 'Cr@ZeeC0nSt@Nt';

	private const RESPONSEKEYS = [
		'success'        => 'scalar',

		'headers'        => 'list',
		'body'           => 'scalar',
		'data'           => 'hash',
		'valid'          => 'hash',
		'object'         => 'scalar',

		'code'           => 'scalar',
		'error'          => 'scalar',
		'messages'       => 'hash',
		'statusText'     => 'scalar',
		'missing'        => 'list',
		'invalid'        => 'list',

		'redirect'       => 'scalar',
		'noPostRedirect' => 'scalar',
		'redirectParams' => 'hash',

		'filename'       => 'scalar',
		'streamHandle'   => 'scalar',
		'rowCallback'    => 'scalar',
		'rows'           => 'list',

		'Stash'          => 'hash'
	];

	public function __toString(): string  {
		return $this->body() ?? $this->error();
	}

	public function jsonSerialize():array { return $this->toArray(); }

	private array $_allData = [];
	public function toArray():array {
		$this->_allData = [];
		foreach(array_keys(self::RESPONSEKEYS) as $key) {
			$this->_allData[$key] = $this->$key();
		}
		// Anything in Stash also goes to main array, so long as it does not
		// conflict with a primary Response property.
		if ($stash = $this->Stash()) {
			$this->_allData = array_merge($this->_allData,
				array_filter($stash,function($v,$k) {
					return array_key_exists($k,self::RESPONSEKEYS) ? false : true;
				},ARRAY_FILTER_USE_BOTH));
		}
		return $this->_allData;
	}

	public function fromArray(array $array):self {
		$this->_allData = $array;
		foreach(array_keys(self::RESPONSEKEYS) as $key) {
			if (isset($array[$key])) {
				$this->$key($array[$key]);
				unset($array[$key]);
			}
		}
		if (!empty($array)) { //anything left, send to Stash
			$this->Stash($array,null,false);
		}
		return $this;
	}

	// undocumented and not encouraged, but need it for the OG Client App:
	// can treat $response like an array:
	// $response = $MVCish->Response(); $response['success'] = true;
	public function offsetExists (mixed $offset):bool {
		if (array_key_exists($offset,self::RESPONSEKEYS)) {
			return NULL != $this->$offset();
		}
		return NULL != $this->Stash($offset);
	}
	public function offsetGet(mixed $offset):mixed {
		if (array_key_exists($offset,self::RESPONSEKEYS)) {
			return $this->$offset();
		}
		return $this->Stash($offset);
	}
	public function offsetSet(mixed $offset, mixed $value):void {
		if (array_key_exists($offset,self::RESPONSEKEYS)) {
			$this->$offset($value);
		}
		else {
			$this->Stash($offset,$value);
		}
	}
	public function offsetUnset(mixed $offset):void	{
		if (array_key_exists($offset,self::RESPONSEKEYS)) {
			$this->$offset(NULL);
		}
		else {
			$this->Stash($offset,NULL);
		}
	}
	public function count():int {
		return count($this->to_Array());
	}
	public function getIterator(): \Traversable {
		return new \ArrayIterator($this->toArray());
	}
	public function serialize(): ?string {
		return serialize($this->toArray());
	}
	public function unserialize(string $data): void {
		$this->fromArray($data);
	}
	public function __serialize(): array {
		return $this->toArray();
	}
	public function __unserialize(array $data): void {
		$this->fromArray($data);
	}



	//****************************************************************************			

	protected bool $respSuccess;
	//public function success(bool|Parameter $value=new Parameter()):bool {
	public function success(bool|string $value=self::dParam):bool {
		// assume success until told otherwise
		return $this->getSetScalar('respSuccess',$value) ?? true;
	}


	protected array $respHeaders = [];
	//public function headers(string $value=new Parameter(),bool $replace=true):?array {
	public function headers(string $value=self::dParam,bool $replace=true):?array {
		return $this->getSetListArray('respHeaders',$value,$replace);
	}

	protected string $respBody;
	//public function body(string|Parameter $value=new Parameter()):?string {
	public function body(string $value=self::dParam):?string {
		return $this->getSetScalar('respBody',$value);
	}
	public function hasBody():bool { return !empty($this->respBody); }

	protected array $respData = [];
	//public function data(null|string|array|Parameter $key=new Parameter(),mixed $value=new Parameter(),bool $replace=true):mixed {
	public function data(null|string|array $key=self::dParam,mixed $value=self::dParam,bool $replace=true):mixed {
		return $this->getSetHashArray('respData',$key,$value,$replace);
	}

	protected array $respValid = [];
	//public function valid(null|string|array|Parameter $key=new Parameter(),mixed $value=new Parameter(),bool $replace=true):mixed {
	public function valid(null|string|array $key=self::dParam,mixed $value=self::dParam,bool $replace=true):mixed {
		return $this->getSetHashArray('respValid',$key,$value,$replace);
	}

	protected object $respObject;
	//public function object(object $value=new Parameter()):?object {
	public function object(object|string $value=self::dParam):?object {
		return $this->getSetScalar('respObject',$value);
	}


	protected int $respCode;
	//public function code(int|Parameter $value=new Parameter()):?int {
	public function code(int|string $value=self::dParam):?int {
		return $this->getSetScalar('respCode',$value);
	}

	protected mixed $respError;
	//public function error(string|Parameter $value=new Parameter()):?string {
	public function error(string|array $value=self::dParam):?string {
		return $this->getSetScalar('respError',$value);
	}


	protected array $respMessages = [];
	//public function messages(null|string|array|Parameter $key=new Parameter(),mixed $value=new Parameter(),bool $replace=true):mixed {
	public function messages(null|string|array $key=self::dParam,mixed $value=self::dParam,bool $replace=true):mixed {
		return $this->getSetHashArray('respMessages',$key,$value,$replace) ?? [];
	}
	// since we know these are simple strings and never arrays of arrays, eliminate
	// the bool and assume if we got an array, it's a replace, else a push.
	public function messageSuccess(string|array $value) {
		return $this->messages('success', $value, is_array($value) ? true : false);
	}
	public function messageError(string|array $value) {
		return $this->messages('error',   $value, is_array($value) ? true : false);
	}
	public function messageInfo(string|array $value) {
		return $this->messages('info',    $value, is_array($value) ? true : false);
	}
	public function messageWarning(string|array $value) {
		return $this->messages('warning', $value, is_array($value) ? true : false);
	}


	protected string $respStatusText;
	//public function statusText(string|Parameter $value=new Parameter()):?string {
	public function statusText(string $value=self::dParam):?string {
		return $this->getSetScalar('respStatusText',$value);
	}

	protected array $respMissing = [];
	//public function missing(string|Parameter $value=new Parameter(),bool $replace=true):?array {
	public function missing(string $value=self::dParam,bool $replace=true):?array {
		return $this->getSetListArray('respMissing',$value,$replace);
	}

	protected array $respInvalid = [];
	//public function invalid(string|Parameter $value=new Parameter(),bool $replace=true):?array {
	public function invalid(string $value=self::dParam,bool $replace=true):?array {
		return $this->getSetListArray('respInvalid',$value,$replace);
	}


	protected string $respRedirect;
	//public function redirect(string|URI|Parameter $value=new Parameter()):mixed {
	public function redirect(string|URI $value=self::dParam):mixed {
		return $this->getSetScalar('respRedirect',$value);
	}
	public function hasRedirect():bool { return !empty($this->redirect); }

	protected string $respNoPostRedirect;
	//public function noPostRedirect(bool|Parameter $value=new Parameter()):?bool {
	public function noPostRedirect(bool|string $value=self::dParam):?bool {
		return $this->getSetScalar('respNoPostRedirect',$value);
	}

	protected array $respRedirectParams = [];
	//public function redirectParams(null|string|array|Parameter $key=new Parameter(),mixed $value=new Parameter(),bool $replace=true):mixed {
	public function redirectParams(null|string|array $key=self::dParam,mixed $value=self::dParam,bool $replace=true):mixed {
		return $this->getSetHashArray('respRedirectParams',$key,$value,$replace);
	}


	protected string $respFilename;
	//public function filename(string|Parameter $value=new Parameter()):?string {
	public function filename(string $value=self::dParam):?string {
		return $this->getSetScalar('respFilename',$value);
	}

	protected mixed $respStreamHandle;
	//public function streamHandle(mixed $value=new Parameter()):mixed {
	public function streamHandle(mixed $value=self::dParam):mixed {
		return $this->getSetScalar('respStreamHandle',$value);
	}

	protected mixed $respRowCallback;
	//public function rowCallback(mixed $value=new Parameter()):mixed {
	public function rowCallback(mixed $value=self::dParam):mixed {
		return $this->getSetScalar('respRowCallback',$value);
	}

	protected array $respRows = [];
	//public function rows(array|Parameter $value=new Parameter(),bool $replace=true):?array {
	public function rows(array|string $value=self::dParam,bool $replace=true):?array {
		return $this->getSetListArray('respRows',$value,$replace);
	}


	// all-purporse datastore for controllers to pass data to templates.
	// could just use 'data', but this keeps things out of your form data.

	protected array $respStash = [];
	//public function Stash(null|string|array|Parameter $key=new Parameter(),mixed $value=new Parameter(),bool $replace=true):mixed {
	public function Stash(null|string|array $key=self::dParam,mixed $value=self::dParam,bool $replace=true):mixed {
		return $this->getSetHashArray('respStash',$key,$value,$replace) ?? [];
	}

	//****************************************************************************
	//****************************************************************************

	public static function cFromString(MVCish $MVCish,string $string):self {
		$response = new self($MVCish);

		$bool = null;
		$response->success(
		// if the string parses to true/false, use it, otherwise assume success
			self::parseBool($string,$bool) ? $response->success($bool) : true
		);

		// either way it's also the literal body of the response
		$response->body($string);

		return $response;
	}

	public static function cFromArray(MVCish $MVCish,array $data):self {
		$response = new self($MVCish);
		$response->fromArray($data);

		if ((!isset($data['success'])) || !is_bool($data['success'])) {

			// if 'success' not explicity found, assume true but bark
			$bool = null;
			if (isset($data['success'])) {
				if (!self::parseBool($data['success'],$bool)) {
					Exception\ServerWarning::throwWarning($MVCish,
						"Could not parse bool from 'success' key in response data; "
						."assuming success, but something may be wrong");
				}
			}
			else {
				Exception\ServerWarning::throwWarning($MVCish,
					"Could not find 'success' key in response data; "
					."assuming success, but something may be wrong");
			}
			$data['success'] = isset($bool) ? $bool : true;
		}
		return $response;
	}

	public static function cFromForeignObject(MVCish $MVCish,object $obj):self {
		$response = new self($MVCish);
		$response->object($obj);

		$bool = null;
		$successMethod = method_exists($obj,'success') ? 'success' :
			(method_exists($obj,'Success') ? 'Success' : null);
		if (isset($successMethod)) {
			try {
				self::parseBool($obj->$successMethod(),$bool);
			}
			catch(\Throwable $e) { }
		}
		if (isset($bool)) {
			$response->success($bool);
		}
		else {
			Exception\ServerWarning::throwWarning($MVCish,
				"Could not parse boolean success from response object; "
				."assuming success, but something may be wrong");
			$response->success(true);
		}
	}

	public static function factory(MVCish $MVCish,mixed $cResponse=null):self {

		// oh hai look at you sexy controller sending us a proper object already
		if (is_a($cResponse,static::class)) return $cResponse;

		// either a parseable bool string or literal text response
		if (is_string($cResponse)) return self::cFromString($MVCish,$cResponse);

		// old school and/or json data.
		if (is_array($cResponse)) return self::cFromArray($MVCish,$cResponse);

		// they responded with some not-Response object.
		// It probably knows how to json serialize itself.
		if (is_object($cResponse)) return self::cFromForeignObect($MVCish,$cResponse);

		// not responding at all may return 1. Treat 1 or 0 as bool.
		// any other int fall through to success but warn.
		if (is_int($cResponse)) {
			$success = $cResponse === 0 ? false : ($cResponse === 1 ? true : null);
		}

		$response = new self($MVCish);
		$success ??=
			// No news is good news. If controller didn't respond at all,
			// and didn't error out, it must have done it's business ok.
			// we wish you would share your feelings, but we won't push.
			empty($cResponse) ? true
	
			// best way to respond if you don't have anything else to say:
			: (is_bool($cResponse) ? $cResponse
		: null);

		if (!isset($success)) {
			$success = true;
			Exception\ServerWarning::throwWarning($MVCish,
				"Could not reliably parse success value from Response"
				."; assuming success, but something may be wrong");
			$response->success(true);
		}
		$response->success($success);
		return $response;	
	}

}
?>
