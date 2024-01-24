<?php
namespace AuntieWarhol\MVCish;

class Response extends Base {

	private const RESPONSEKEYS = [
		'success'        => 'scalar',

		'headers'        => 'array_push',
		'body'           => 'scalar',
		'data'           => 'array',
		'valid'          => 'valid',
		'object'         => 'scalar',

		'code'           => 'scalar',
		'error'          => 'scalar',
		'messages'       => 'array',
		'statusText'     => 'scalar',
		'missing'        => 'array_push',
		'invalid'        => 'array_push',

		'redirect'       => 'scalar',
		'noPostRedirect' => 'scalar',
		'redirectParams' => 'array',

		'filename'       => 'scalar',
		'streamHandle'   => 'scalar',
		'rowCallbakc'    => 'scalar',
		'rows'           => 'array_push',

		'Stash'          => 'array'
	];

	public function __toString(): string  {
		return $this->body() ?? $this->error();
	}

	public function jsonSerialize():array { return $this->toArray(); }

	public function toArray():array {
		$resp = [];
		foreach(array_keys(self::RESPONSEKEYS) as $key) {
			$resp[$key] = $this->$key();
		}
		return $resp;
	}


	//****************************************************************************			

	protected bool $respSuccess;
	public function success(bool $set=null,bool $delete=false):bool {
		// assume success until told otherwise
		return $this->getSetScalar('respSuccess',$set,$delete) ?? true;
	}


	protected array $respHeaders = [];
	public function headers(string $set=null,array $setAll=null,array $opts=null):?array {
		return $this->getPushArray('respHeaders',$set,$setAll,$opts);
	}

	protected string $respBody;
	public function body(string $set=null,bool $delete=false):?string {
		return $this->getSetScalar('respBody',$set,$delete);
	}
	public function hasBody():bool { return !empty($this->respBody); }

	protected array $respData = [];
	public function data(string|bool|array $key=null,mixed $set=null,string $action=null):mixed {
		return $this->getSetArray('respData',$key,$set,$action);
	}

	protected array $respValid = [];
	public function valid(string|bool|array $key=null,mixed $set=null,string $action=null):mixed {
		return $this->getSetArray('respValid',$key,$set,$action);
	}

	protected object $respObject;
	public function object(object $set=null,bool $delete=false):?object {
		return $this->getSetScalar('respObject',$set,$delete);
	}


	protected int $respCode;
	public function code(int $set=null,bool $delete=false):?int {
		return $this->getSetScalar('respCode',$set,$delete);
	}

	protected string $respError;
	public function error(string $set=null,bool $delete=false):?string {
		return $this->getSetScalar('respError',$set,$delete);
	}


	protected array $respMessages = [];
	public function messages(string|bool|array $key=null,mixed $set=null,string $action=null):array {
		return $this->getSetArray('respMessages',$key,$set,$action) ?? [];
	}
	public function messageSuccess(string|bool|array $set) {
		return $this->messages('success', $set,is_array($set) ? 'merge' : 'replace');
	}
	public function messageError(string|bool|array $set) {
		return $this->messages('error',   $set,is_array($set) ? 'merge' : 'replace');
	}
	public function messageInfo(string|bool|array $set) {
		return $this->messages('info',    $set,is_array($set) ? 'merge' : 'replace');
	}
	public function messageWarning(string|bool|array $set) {
		return $this->messages('warning', $set,is_array($set) ? 'merge' : 'replace');
	}


	protected string $respStatusText;
	public function statusText(string $set=null,bool $delete=false):?string {
		return $this->getSetScalar('respStatusText',$set,$delete);
	}

	protected array $respMissing = [];
	public function missing(string $set=null,array $setAll=null,array $opts=null):?array {
		return $this->getPushArray('respMissing',$set,$setAll,$opts);
	}

	protected array $respInvalid = [];
	public function invalid(string $set=null,array $setAll=null,array $opts=null):?array {
		return $this->getPushArray('respInvalid',$set,$setAll,$opts);
	}


	protected string $respRedirect;
	public function redirect(string|URI $set=null,bool $delete=false):mixed {
		return $this->getSetScalar('respRedirect',$set,$delete);
	}
	public function hasRedirect():bool { return !empty($this->redirect); }

	protected string $respNoPostRedirect;
	public function noPostRedirect(bool $set=null,bool $delete=false):?bool {
		return $this->getSetScalar('respNoPostRedirect',$set,$delete);
	}

	protected array $respRedirectParams = [];
	public function redirectParams(string|bool|array $key=null,string $set=null,string $action=null):?array {
		return $this->getSetArray('respRedirectParams',$key,$set,$action);
	}


	protected string $respFilename;
	public function filename(string $set=null,bool $delete=false):?string {
		return $this->getSetScalar('respFilename',$set,$delete);
	}

	protected mixed $respStreanHandle;
	public function streamHandle(mixed $set=null,bool $delete=false):mixed {
		return $this->getSetScalar('respStreamHandle',$set,$delete);
	}

	protected mixed $respRowCallback;
	public function rowCallback(mixed $set=null,bool $delete=false):mixed {
		return $this->getSetScalar('respRowCallback',$set,$delete);
	}

	protected array $respRows = [];
	public function rows(array $set=null,array $setAll=null,array $opts=null):?array {
		return $this->getPushArray('respRows',$set,$setAll,$opts);
	}


	// all-purporse datastore for controllers to pass data to templates.
	// could just use 'data', but this keeps things out of your form data.

	protected array $respStash = [];
	public function Stash(string|bool|array $key=null,mixed $set=null,string $action=null):?array {
		return $this->getSetArray('respStash',$key,$set,$action);
	}

	//****************************************************************************
	//****************************************************************************

	public static function fromString(\AuntieWarhol\MVCish\MVCish $MVCish,string $string):self {
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

	public static function fromArray(\AuntieWarhol\MVCish\MVCish $MVCish,array $data):self {
		$response = new self($MVCish);

		foreach(self::RESPONSEKEYS as $key => $type) {

			// if 'success' not explicity found, assume true but bark
			if ($key == 'success') {
				$bool = null;
				if (isset($data[$key])) {
					if (!self::parseBool($data[$key],$bool)) {
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
				$data[$key] = isset($bool) ? $bool : true;
			}
			if (isset($data[$key])) {
				if ($type == 'scalar') {
					$response->$key($data[$key]);
				}
				else if ($type == 'array') {
					$response->$key($data[$key]);
				}
				else if ($type == 'array_push') {
					$response->$key(null,$data[$key]);
				}
				unset($data[$key]);
			}
		}

		if ((!$response->data()) & !empty($data)) {
			// if anything left, take that as data if not set
			$response->data(null,null,$data);
		}
		return $response;
	}

	public static function fromForeignObject(\AuntieWarhol\MVCish\MVCish $MVCish,object $obj):self {
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

	public static function factory(\AuntieWarhol\MVCish\MVCish $MVCish,mixed $cResponse=null):self {

		// oh hai look at you sexy controller sending us a proper object already
		if (is_a($cResponse,static::class)) return $cResponse;

		// either a parseable bool string or literal text response
		if (is_string($cResponse)) return self::fromString($MVCish,$cResponse);

		// old school and/or json data.
		if (is_array($cResponse)) return self::fromArray($MVCish,$cResponse);

		// they responded with some not-Response object.
		// It probably knows how to json serialize itself.
		if (is_object($cResponse)) return self::fromForeignObect($MVCish,$cResponse);

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
