<?php
namespace AuntieWarhol\MVCish;

class Response extends Base {

	private const RESPONSEKEYS = [
		'success'        => 'scalar',
		'headers'        => 'array_push',
		'body'           => 'scalar',
		'data'           => 'array',
		'object'         => 'scalar',

		'code'           => 'scalar',
		'error'          => 'scalar',
		'messages'       => 'array',
		'statusText'     => 'scalar',

		'redirect'       => 'scalar',
		'noPostRedirect' => 'scalar',
		'redirectParams' => 'array',

		'filename'       => 'scalar',
		'streamHandle'   => 'scalar',
		'rowCallbakc'    => 'scalar',
		'rows'           => 'array_push',
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
	public function success(bool $set=null):bool {
		// assume success until told otherwise
		return $this->getSetScalar('respSuccess',$set) ?? true;
	}



	protected array $respHeaders = [];
	public function headers(string $set=null,array $setAll=null):?array {
		return $this->getPushArray('respHeaders',$set,$setAll);
	}

	protected string $respBody;
	public function body(string $set=null):?string {
		return $this->getSetScalar('respBody',$set);
	}
	public function hasBody():bool { return !empty($this->respBody); }

	protected array $respData = [];
	public function data(string $key=null,mixed $set=null,array $setAll=null):mixed {

		// prolly not gonna keep this but BF needs it for first merge
		// turn ->data('redirect',$url) into ->redirect($url)
		if (isset($key) && array_key_exists($key,self::RESPONSEKEYS)) {
			return $this->$key($set);
		}

		return $this->getSetArray('respData',$key,$set,$setAll);
	}

	protected object $respObject;
	public function object(object $set=null):?object {
		return $this->getSetScalar('respObject',$set);
	}


	protected int $respCode;
	public function code(int $set=null):?int {
		return $this->getSetScalar('respCode',$set);
	}

	protected string $respError;
	public function error(string $set=null):?string {
		return $this->getSetScalar('respError',$set);
	}

	protected array $respMessages = [];
	public function messages(string $key=null,mixed $set=null,array $setAll=null):?array {
		return $this->getSetArray('respMessages',$key,$set,$setAll);
	}

	protected string $respStatusText;
	public function statusText(string $set=null):?string {
		return $this->getSetScalar('respStatusText',$set);
	}


	protected string $respRedirect;
	public function redirect(string|URI $set=null):mixed {
		return $this->getSetScalar('respRedirect',$set);
	}
	public function hasRedirect():bool { return !empty($this->redirect); }

	protected string $respNoPostRedirect;
	public function noPostRedirect(bool $set=null):?bool {
		return $this->getSetScalar('respNoPostRedirect',$set);
	}

	protected array $respRedirectParams = [];
	public function redirectParams(string $key=null,string $set=null,array $setAll=null):?array {
		return $this->getSetArray('respRedirectParams',$key,$set,$setAll);
	}


	protected string $respFilename;
	public function filename(string $set=null):?string {
		return $this->getSetScalar('respFilename',$set);
	}

	protected mixed $respStreanHandle;
	public function streamHandle(mixed $set=null):mixed {
		return $this->getSetScalar('respStreamHandle',$set);
	}

	protected mixed $respRowCallback;
	public function rowCallback(mixed $set=null):mixed {
		return $this->getSetScalar('respRowCallback',$set);
	}

	protected array $respRows = [];
	public function rows(array $set=null,array $setAll=null):?array {
		return $this->getPushArray('respRows',$set,$setAll);
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
					$response->$key(null,null,$data[$key]);
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
		if (is_a($cResponse,self)) return $cResponse;

		// either a parseable bool string or literal text response
		if (is_string($cResponse)) return self::fromString($MVCish,$cResponse);

		// old school and/or json data.
		if (is_array($cResponse)) return self::fromArray($MVCish,$cResponse);

		// they responded with some not-Response object.
		// It probably knows how to json serialize itself.
		if (is_object($cResponse)) return self::fromForeignObect($MVCish,$cResponse);

		$response = new self($MVCish);
		$success =
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
