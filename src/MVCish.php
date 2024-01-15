<?php
namespace AuntieWarhol\MVCish;

use \Monolog\Logger;
use \Monolog\Handler\StreamHandler;
use \Monolog\Formatter\LineFormatter;
use \PHPMailer\PHPMailer\PHPMailer;
use \Plasticbrain\FlashMessages\FlashMessages;


class MVCish {

	public $ENV     = '';
	public $options = [];

	// CONSTRUCT

	public function __construct($options = []) {

		// stash the options and configured environment
		$this->options = $options;
		if (isset($this->options['environment']))
			$this->ENV = $this->options['environment'];

		// register error handlers
		if (empty($GLOBALS['MVCISH_IGNORE_ERRORS'])) {
			//error_log("using MVCish error_handler");

			set_error_handler(function($errno, $errstr, $errfile, $errline){
				$this->_error_handler($errno, $errstr, $errfile, $errline);
			},E_ALL);
			register_shutdown_function(function() {
				if ($error = error_get_last()) {
					$this->_error_handler($error['type'],$error['message'],$error['file'],$error['line']);
				}
			});
		}
		//init PHP session if not command line session
		if (!(php_sapi_name() == "cli")) {
			if (empty(session_id())) { session_start(); }
		}

	}

	private function _error_handler($errno, $errstr, $errfile, $errline) {
		$er = error_reporting();
		if ($er == 0 || $er == 4437) return true; //4437=php8 hack

		$msg = [$this->_translate_errno($errno).": $errstr; line $errline:$errfile"];

		$exception = null;
		$msgMethod = null;
		if (in_array($errno,[E_USER_ERROR,E_CORE_ERROR,E_ERROR,E_PARSE,E_COMPILE_ERROR])) {
			$msg[] = "TRACE: ".$this->_get_caller_info();
			$exception = new \AuntieWarhol\MVCish\Exception\ServerError();
			$msgMethod = "error";
		}
		elseif (($errno == E_NOTICE) && (substr($errstr,0,11) == 'unserialize')) {
			// hack to ignore this warning, because the only way to test if something is serialized
			// is to try and unserialize it, and you can't catch or make it not throw notices. 
		}
		else {
			$msgMethod = "warning";
		}

		if ($msgMethod) {
			try {
				foreach ($msg as $m) {
					$this->log('MVCish')->$msgMethod($m);
				}
			} catch (\Exception $e) {
				$msg[] = "Additional error encountered writing to MVCish log: ".$e->getMessage();
				foreach ($msg as $m) {
					error_log($m);
				}
			}
		}
		if ($exception) {
			$this->processExceptionResponse($exception);
			exit(1);
		}
		return true;
	}

	private function _translate_errno($errno) {
		$e_type = '';
		switch ($errno) {
			case 1: $e_type = 'E_ERROR'; break;
			case 2: $e_type = 'E_WARNING'; break;
			case 4: $e_type = 'E_PARSE'; break;
			case 8: $e_type = 'E_NOTICE'; break;
			case 16: $e_type = 'E_CORE_ERROR'; break;
			case 32: $e_type = 'E_CORE_WARNING'; break;
			case 64: $e_type = 'E_COMPILE_ERROR'; break;
			case 128: $e_type = 'E_COMPILE_WARNING'; break;
			case 256: $e_type = 'E_USER_ERROR'; break;
			case 512: $e_type = 'E_USER_WARNING'; break;
			case 1024: $e_type = 'E_USER_NOTICE'; break;
			case 2048: $e_type = 'E_STRICT'; break;
			case 4096: $e_type = 'E_RECOVERABLE_ERROR'; break;
			case 8192: $e_type = 'E_DEPRECATED'; break;
			case 16384: $e_type = 'E_USER_DEPRECATED'; break;
			case 30719: $e_type = 'E_ALL'; break;
			default: $e_type = 'E_UNKNOWN'; break;
		}
		return $e_type;
	}
	function _get_caller_info() {
		$c = '';
		$file = '';
		$func = '';
		$class = '';
		$trace = debug_backtrace();
		if (isset($trace[2])) {
			$file = $trace[1]['file'];
			$func = $trace[2]['function'];
			if ((substr($func, 0, 7) == 'include') || (substr($func, 0, 7) == 'require')) {
				$func = '';
			}
		} else if (isset($trace[1])) {
			$file = $trace[1]['file'];
			$func = '';
		}
		if (isset($trace[3]['class'])) {
			$class = $trace[3]['class'];
			$func = $trace[3]['function'];
			$file = $trace[2]['file'];
		} else if (isset($trace[2]['class'])) {
			$class = $trace[2]['class'];
			$func = $trace[2]['function'];
			$file = $trace[1]['file'];
		}
		if ($file != '') $file = basename($file);
		$c = $file . ": ";
		$c .= ($class != '') ? ":" . $class . "->" : "";
		$c .= ($func != '') ? $func . "(): " : "";
		return($c);
	}

	// CONFIG *************************

	private $_config = null;
	private function initConfig() {
		if (isset($this->_config)) return true;
		$configDir = $this->getAppDirectory().'config';
		if (is_dir($configDir)) $configDir .= DIRECTORY_SEPARATOR;
		else $configDir = $this->getAppDirectory();

		// load configuration
		$def_config = $configDir.'app-config.php';
		if (file_exists($def_config) && ($config = include($def_config))) {
			if (is_array($config)) {
				$this->_config = $config;
			}
			else {
				$this->log('MVCish')->error("Failed to parse array from $def_config");
			}
		}
		if ($this->ENV)	{
			$env_config = $configDir.'app-config-'.strtolower($this->ENV).'.php';

			if (file_exists($env_config) && ($config_env = include($env_config))) {
				if (is_array($config_env)) {
					$this->_config =
						array_replace($this->_config,$config_env);
				}
				else {
					$this->log('MVCish')->error("Failed to parse array from $env_config");
				}
			}
		}
		if (!isset($this->_config)) $this->_config = [];
		//$this->log('MVCish')->debug("MVCish Config",$this->_config);
	}
	public function Config($key) {
		$this->initConfig();
		if (array_key_exists($key,$this->_config)) {
			return $this->_config[$key];
		}
		return;
	}

	private $appDirectory = null;
	public function getAppDirectory() {
		if (!isset($this->appDirectory)) {
			if (empty($this->options['appDirectory'])) {
				trigger_error("Using MVCish without setting an application directory is discouraged; using tmpfiles.", E_USER_WARNING);
				$this->appDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR;
			}
			else {
				$this->appDirectory =
					rtrim($this->options['appDirectory'],DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
			}
		}
		return $this->appDirectory;
	}
	private $runtimeDirectory = null;
	public function getRuntimeDirectory() {
		if (!isset($this->runtimeDirectory)) {
			// you can set this directly in options
			// or we will create it in the appDirectory
			if (empty($this->options['runtimeDirectory'])) {
				$this->runtimeDirectory = $this->getAppDirectory()."runtime".DIRECTORY_SEPARATOR;

			}
			else {
				$this->runtimeDirectory =
					rtrim($this->options['runtimeDirectory'],DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
			}
			if (!file_exists($this->runtimeDirectory)) {
				if (!mkdir($this->runtimeDirectory,0755,true)) {
					throw new \AuntieWarhol\MVCish\Exception\ServerError("Failed to find or create runtime directory");
				}
			}
		}
		return $this->runtimeDirectory;
	}

	private $rootTemplateDirectory = null;
	public function getTemplateDirectory($reset = false) {
		if ($reset || !isset($this->rootTemplateDirectory)) {

			// set the default template directory if not optioned or configed
			if (isset($this->options['templateDirectory'])) {
				$this->rootTemplateDirectory = $this->options['templateDirectory'];
			}
			elseif ($td = $this->Config('TEMPLATE_DIRECTORY')) {
				$this->rootTemplateDirectory = $td;
			}
			else {
				$defaultTD = $this->getAppDirectory().'templates';
				if (is_dir($defaultTD)) {
					$this->rootTemplateDirectory = $defaultTD;
				}
			}
			// ensure single trailing slash
			$this->rootTemplateDirectory =
				rtrim($this->rootTemplateDirectory,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
		}
		return $this->rootTemplateDirectory;
	}

	// MODEL ************************

		/* 'Model' is only very loosely coupled; just looks for the requested class,
			Can configure to auto-prepend part of a namespace.
			examples:
				$user = $MVCish->Model('\Models\UserQuery'); // returns '\Models\UserQuery'
				$user = $MVCish->Model('UserQuery');         // same, if 'Models\' configured as MODEL_NAMESPACE

			A model_initialize function can be passed in MVCish options to do any
			setup work needed for the model when MVCish starts.
		*/

	private $modelInited = false;
	private $modelNamespace = null;
	public function initModel() {
		if ($this->modelInited) return true;
		// INIT MODEL if so configured. can come from
		// options or config (options take priority)
		if (
			(isset($this->options['MODEL']) && ($mconfig = $this->options['MODEL'])) ||
			($mconfig = $this->Config('MODEL'))
		) {
			if (array_key_exists('INIT',$mconfig)) {
				$mconfig['INIT']($this);
			}
			if (array_key_exists('NAMESPACE',$mconfig)) {
				$this->modelNamespace = $mconfig['NAMESPACE'];
			}
		}
		$this->modelInited = true;
	}
	private function getValidModelClass($class) {
		if (substr($class,0,1) == '\\') {
			// if passed \Absolute\Class
			$fullClass =  $class;
		}
		elseif ($namespace = $this->modelNamespace) {
			$fullClass = $namespace.$class;
		}
		else {
			// try it as is
			$fullClass = $class;
		}
		if (class_exists($fullClass)) return $fullClass;
	}

	public function Model($class) {

		$this->initModel();
		if ($validClass = $this->getValidModelClass($class)) {
			return new $validClass;
		}
	}


	// VIEW *********************************

	private $view;
	public function View() {
		if (!$this->view) {
			$this->view = new View($this);
		}
		return $this->view;
	}


	// CONTROLLER ***************************

	public function Run($controller=null,$options=[]) {
		$this->options = array_replace_recursive($this->options,$options);
		try {
			// Authorize
			if ($this->authorize()) {
				// Run
				$this->runController($controller);
			}

		} catch (\Exception $e) {
			$this->logExceptionMessage($e,"Caught Exception in runController:");
			return $this->processExceptionResponse($e);
		}

		// we try/catch view exceptions separately from authorize/controller,
		// so that non-fatal exceptions in controller can process view normally
		try {
			// post-processing & view
			$this->processResponse();

		} catch (\Exception $e) {
			$this->logExceptionMessage($e,"Caught Exception in processResponse:");
			return $this->processExceptionResponse($e);
		}
		return true;
	}

	private function logExceptionMessage($e,$basemsg) {
		$logAs = 'error';
		$msg = $e->getMessage() ?: '';
		if ($code = $e->getCode()) {

			if (in_array($code,[404,401,301])) {
				// skip basemsg & getMessage
				$msg = "$code: ".$_SERVER['REQUEST_URI'];
				if (isset($_SERVER['HTTP_REFERER']))
					$msg .= ' (ref: '.$_SERVER['HTTP_REFERER'].')';

				// don't need to log these in prod
				if (in_array($code,[401,301])) {
					$logAs = 'debug';
				}
			}
			else {
				$msg = "$basemsg $code: $msg";
				$msg .= ' ('.$e->getFile().': '.$e->getLine().')';
				if ($this->ENV == 'LOCAL')
					$msg .= "; TRACE: ".$this->_get_caller_info();
			}
		}
		else {
			$msg = $basemsg.' '.$msg.' ('.$e->getFile().': '.$e->getLine().')';
			if (isset($_SERVER['REQUEST_URI']))  $msg .= ' ('.$_SERVER['REQUEST_URI'].')';
			if (isset($_SERVER['HTTP_REFERER'])) $msg .= ' ('.$_SERVER['HTTP_REFERER'].')';
			//if ($this->ENV == 'LOCAL')
				$msg .= "; TRACE: ".$this->_get_caller_info();
		}
		$this->log('MVCish')->$logAs($msg);
	}

	private function authorize() {
		/* MVCish doesn't know anything about Authentication/Authorization.
			but if you set an object on $MVCish->Auth(), and that object has
			an "Authorize" method, we'll call it, and pass it anything passed
			as an 'Authorize' option. The method should return true if authorized.
			If it returns false, we'll throw an unauthorized exception. Your object
			can also throw its own \AuntieWarhol\MVCish\Exception if you want to control
			the messaging (or throw Forbidden instead of Unauthorized, etc)
		*/
		if ($this->Auth() && is_callable([$this->Auth(),'Authorize'])) {
			if (!$this->Auth()->Authorize(
				isset($this->options['Authorize']) ? $this->options['Authorize'] : null
			)) {
				throw new \AuntieWarhol\MVCish\Exception\Unauthorized();
			}
		} // else assume authorized
		return true;
	}


	public $Response = ['success' => true]; //assume success
	public $pathArgs = null;
	public $controllerName = null;

	private function runController($controller) {
		/*
			we may or may not have a single point of entrance;
			if we do, we figured out from the url what controller you wanted
			and ran it. Otherwise, the url took you directly to a php file as
			usual, and that file ran us, passing the 'controller' as a closure:
		*/

		if ((!empty($controller)) || ($controller = $this->getUrlController())) {

			$response = null;
			if (is_callable($controller)) {
				$response = $controller($this);
			}
			elseif (is_string($controller)) {
				$self = $this;
				$response = include($controller);
			}

			// Response should typically be an array, with a 'success' key,
			// along with any other keys appropriate for the situation. However it
			// could also be a bool, in which case we'll convert it like so:
			if (is_bool($response)) $response = ['success' => $response];

			// otherwise we'll take any evaluates-true response you send.
			// for example other than the typical array, you might send an object
			// that can serialize itself for the json view.
			// if the view can't handle your response, that's on you.

			if (!empty($response)) {
				$this->Response = $response;
				//$this->log('MVCish')->debug("Controller Respone: ".json_encode($this->Response,true));
			}
			// if controller ran but didn't send any response,
			// assume all is well and use the default response.
		}
	}

	public function getUrlController($requestUri=null,$attemptDecode=true) {
		$ctrlDirectory = $this->getAppDirectory().'controllers';

		if (empty($requestUri)) $requestUri = $_SERVER['REQUEST_URI'];
		$urlPath = parse_url($requestUri,PHP_URL_PATH);

		// if initial url is dir, convert separator to system directory separator and add index
		if (substr($urlPath,-1) == '/') {
			$urlPath = rtrim($urlPath,'/').DIRECTORY_SEPARATOR.'index.php';
		}

		$pathArgs = [];
		while ($urlPath && ($urlPath != '/')) {
			//$this->log()->debug("urlPath=".$urlPath);
		
			$pathinfo = pathinfo($urlPath);
			$fullfile = $ctrlDirectory.$urlPath;

			// if dir, try index.php
			if (is_dir($fullfile)) {
				$fullfile .= DIRECTORY_SEPARATOR.'index.php';
				if (file_exists($fullfile)) {
					//$this->log()->debug("Found controller: $fullfile, args=".print_r($pathArgs,true));
					$this->pathArgs = $pathArgs;
					$this->controllerName = substr($fullfile,-(strlen($fullfile) - strlen($ctrlDirectory)));
					return $fullfile;
				}
				//$this->log()->debug("No url controller $fullfile");
			}

			if (!isset($pathinfo['extension'])) $fullfile .= '.php';
			if (substr($fullfile,-4) == '.php') {

				if (file_exists($fullfile)) {
					//$this->log()->debug("Found controller: $fullfile, args=".print_r($pathArgs,true));
					$this->pathArgs = $pathArgs;
					$this->controllerName = substr($fullfile,-(strlen($fullfile) - strlen($ctrlDirectory)));
					return $fullfile;
				}
				//$this->log()->debug("No url controller $fullfile");
			}
			//else {
				//$this->log()->debug("skip not-php file $fullfile");
			//}

			$urlPath = $pathinfo['dirname'] === '.' ? null : $pathinfo['dirname'];
			if ($pathinfo['basename'] != 'index.php') {
				array_unshift($pathArgs,$pathinfo['basename']);
			}
		}

		// look for urls with ? misencoded as %3F
		if ($attemptDecode && ($decoded = urldecode($requestUri))) {
			if ($decoded != $requestUri) {
				//$this->log()->debug("decoding changed requestUrl to: ".$decoded);
				if ($decodedFile = $this->getUrlController($decoded,false)) {
					//$this->log()->debug("found decoded controller: ".$decodedFile);
					return $this->redirect($decoded,301);
					//throw new \AuntieWarhol\MVCish\Exception\MovedPermanently($decoded);
				}
			}
		}
		//$this->log()->warning("no controller found for ".$_SERVER['REQUEST_URI']);
	}

	private function processExceptionResponse($e) {
		// if it's our exception (or a subclass of our exceptions),
		// then we the exception message is the error we want to return
		if ($e instanceof \AuntieWarhol\MVCish\Exception) {
			$this->Response = ['success' => false,
				'code'       => $e->getCode(),
				"error"      => $e->getMessage(),
				'messages'   => ['error' => $e->getMessage()],
				'statusText' => $e->statusText()
			];
			// custom method on our Exceptions to tell us if/where we should redirect
			if ($redirect = $e->getRedirectUrl()) {

				// internal redirect typically used to redirect to login on Unauthorized error
				if ($e->isInternalRedirect()) {
					$parsed = parse_url($redirect);
					if (empty($parsed['host']) ||
						($parsed['host'] == $this->uri()->getCurrentHost())
					) {
						$newReqUri = $parsed['path']
							.(isset($parsed['query']) ? '?'.$parsed['query'] : '');
						if ($controller = $this->getUrlController($newReqUri)) {

							$this->processMessages();
							if (isset($parsed['query'])) parse_str($parsed['query'],$_GET);
							$_SERVER['REQUEST_URI'] = $newReqUri;
							$this->options['template'] = null;
							//$this->log('MVCish')->debug('do internal redirect: '.$redirect);
							return $this->Run($controller);
						}
					}
				}
				// otherwise, or if didn't find controller above, do server redirect
				$this->Response['redirect'] = $redirect;
			}
		}
		// any other/unexpected exceptions return generic server error
		else {
			$this->Response = ['success' => false,
				'code'     => \AuntieWarhol\MVCish\Exception::SERVER_ERROR,
				"error"    => \AuntieWarhol\MVCish\Exception::serverError,
				'messages' => ['error' => \AuntieWarhol\MVCish\Exception::serverError],
				'statusText' => \AuntieWarhol\MVCish\Exception::serverError
			];
		}
		return $this->processResponse();
	}


	private $responseMessageTypes = ['info','success','warning','error'];

	private function processResponse() {
		if (isset($this->options['beforeRender']) && is_callable($this->options['beforeRender'])) {
			// if optional beforeRender hook returns false, abort
			if (!$this->options['beforeRender']($this)) {
				return true;
			}
		}
		// skip if controller flags that it already did the work
		if (!empty($this->options['rendered'])) return true;

		if (is_array($this->Response)) {
			// render any non-sucess as Error
			if (empty($this->Response['success'])) {
				return $this->View()->renderError();
			}
		}
		// render the View normally
		return $this->View()->renderView();
	}

	public function processMessages() {
		if (is_array($this->Response)) {
			//array-ify any scalar messages and flash-display/store them
			if (isset($this->Response) && isset($this->Response['messages'])) {
				foreach($this->responseMessageTypes AS $mtype) {
					if (isset($this->Response['messages'][$mtype])) {
						if (!is_array($this->Response['messages'][$mtype])) {
							$this->Response['messages'][$mtype] = [$this->Response['messages'][$mtype]];
						}
						foreach($this->Response['messages'][$mtype] AS $m) {
							$this->flashmsg()->$mtype($m);
						}
					}
				}
			}
		}
	}



	// AUTH ***************************

		// we don't have an integrated user auth package; can pass in an
		// object from other library for us to stash and make available

	private $_auth;
	public function Auth($authobj = false) {
		if ($authobj !== false) {
			$this->_auth = $authobj;
		}
		return $this->_auth;
	}

		// convenience function. auth object must provide custom method to support it
	public function User($user = false) {
		if ($auth = $this->Auth()) {
			if (is_callable([$auth,'getMVCishUser'])) {
				return $auth->getMVCishUser($this);
			}
		}
	}



	// UTILS / MISC ***********************

	private $_logfile;
	private $_logs = [];
	public function log($channel = null) {

		$name = $this->Config('APPLICATION_NAME') ?	$this->Config('APPLICATION_NAME') : 'MVCish';
		if (!$channel) $channel = $name;

		if (!array_key_exists($channel,$this->_logs)) {

			if (!$this->_logfile) {
				$logConfig = $this->Config('LOGFILE');

				$logfile = (isset($logConfig) && isset($logConfig['LOGFILE'])) ?
					$logConfig['LOGFILE'] :
					$this->getRuntimeDirectory().'logs'.DIRECTORY_SEPARATOR.$name.'.log';

				$logdir = pathinfo($logfile,PATHINFO_DIRNAME);
				if (!file_exists($logdir)) {
					if (!mkdir($logdir,0755,true)) {
						throw new \AuntieWarhol\MVCish\Exception\ServerError("Failed to find or create logdir");
					}
				}
				if (!file_exists($logfile)) {
					if (!touch($logfile))
						throw new \AuntieWarhol\MVCish\Exception\ServerError("Failed to create logfile");
				}
				$this->_logfile = $logfile;
			}

			$logger  = new Logger($channel);
			$logEnv  = (($this->ENV == 'PROD') && (php_sapi_name() != "cli")) ?
				Logger::WARNING : Logger::DEBUG;
			$handler = new StreamHandler($this->_logfile,$logEnv);
			$handler->setFormatter(new LineFormatter(null,null,false,true));
			$logger->pushHandler($handler);
			$this->_logs[$channel] = $logger;
		}
		return $this->_logs[$channel];
	}

	private $_uri;
	public function uri() {
		if (!$this->_uri) {
			$this->_uri = new Util\URI();
		}
		return $this->_uri;
	}
	// shortcut alias
	public function uriFor($uri=null,$params=[]) {
		return $this->uri()->uriFor($uri,$params);
	}

	private $_validator;
	public function validator() {
		if (!$this->_validator) {
			$this->_validator = new Util\Validator($this);
		}
		return $this->_validator;
	}

	private $_domainParser;
	public function domainParser() {
		if (!$this->_domainParser) {
			$this->_domainParser = new Util\DomainParser($this);
		}
		return $this->_domainParser;
	}

	private $_flashmsg;
	public function flashmsg() {
		if (!$this->_flashmsg) {
			$this->_flashmsg  = new \Plasticbrain\FlashMessages\FlashMessages();
			$this->_flashmsg->setMsgWrapper("<div class='%s'>%s</div>");
		}
		return $this->_flashmsg;
	}

	public function mail($emaildata = []) {
		$mail = new PHPMailer();
		$mail->CharSet = 'UTF-8';
		$mail->Encoding = "base64";
		$mail->Debugoutput = $this->log('PHPMailer');

		if ($mailcfg = $this->Config('MAIL')) {
			//TODO: add other PHPMailer options as becomes necessary
			if (!empty($mailcfg['isSendmail'])) {
				$mail->isSendmail();
			}
			elseif (!empty($mailcfg['isSMTP'])) {
				$mail->isSMTP();
				$mail->SMTPDebug = array_key_exists('SMTPDebug',$mailcfg) ? $mailcfg['SMTPDebug'] : 0;
				$mail->Host      = array_key_exists('Host',$mailcfg)      ? $mailcfg['Host']      : 'localhost';
				$mail->Port      = array_key_exists('Port',$mailcfg)      ? $mailcfg['Port']      : '25';
				if (array_key_exists('Username',$mailcfg))   $mail->Username   = $mailcfg['Username'];
				if (array_key_exists('Password',$mailcfg))   $mail->Password   = $mailcfg['Password'];
				if (array_key_exists('SMTPSecure',$mailcfg)) $mail->SMTPSecure = $mailcfg['SMTPSecure'];
				if (array_key_exists('SMTPAuth',$mailcfg))   $mail->SMTPAuth   = $mailcfg['SMTPAuth'];
				if (array_key_exists('AuthType',$mailcfg))   $mail->AuthType   = $mailcfg['AuthType'];
			}

			if (array_key_exists('DEFAULT_FROM',$mailcfg))    $defaultFrom = $mailcfg['DEFAULT_FROM'];
			if (array_key_exists('DEFAULT_TO',$mailcfg))      $defaultFrom = $mailcfg['DEFAULT_TO'];
			if (array_key_exists('DEFAULT_SUBJECT',$mailcfg)) $defaultFrom = $mailcfg['DEFAULT_SUBJECT'];
		}

		// pass emaildata as an array and we'll do the work here and
		// return the result of calling send()
		if ($emaildata) {
			$defaultFrom = $defaultTo = $defaultSub = $overrideTo = $autoCC = $autoBCC = $render = null;

			if ($mailcfg = $this->Config('MAIL')) {
				if (array_key_exists('DEFAULT_FROM',$mailcfg))    $defaultFrom = $mailcfg['DEFAULT_FROM'];
				if (array_key_exists('DEFAULT_TO',$mailcfg))      $defaultTo   = $mailcfg['DEFAULT_TO'];
				if (array_key_exists('DEFAULT_SUBJECT',$mailcfg)) $defaultSub  = $mailcfg['DEFAULT_SUBJECT'];
				if (array_key_exists('OVERRIDE_TO',$mailcfg))     $overrideTo  = $mailcfg['OVERRIDE_TO'];
				if (array_key_exists('AUTOCC_TO',$mailcfg))       $autoCC      = $mailcfg['AUTOCC_TO'];
				if (array_key_exists('AUTOBCC_TO',$mailcfg))      $autoBCC     = $mailcfg['AUTOBCC_TO'];
			}

			// addresses can be  'email@address.com' or 'Name <email@address.com>'

			if ($from = array_key_exists('From',$emaildata) ? $emaildata['From'] : $defaultFrom) {
				$args = self::_parseEmailAddress($from);
				$mail->setFrom(...$args);
			}

			// To & CC can be singles or arrays

			if ($to = $overrideTo ? $overrideTo :
				(array_key_exists('To',$emaildata) ? $emaildata['To'] : $defaultTo)
			) {
				if (!is_array($to)) $to = [$to];
				foreach ($to as $t) {
					$args = self::_parseEmailAddress($t);
					$mail->addAddress(...$args);
				}
			}

			$cc = $autoCC ? (is_array($autoCC) ? $autoCC : [$autoCC]) : [];
			if (array_key_exists('CC',$emaildata))
				$cc = array_merge($cc, is_array($emaildata['CC']) ? $emaildata['CC'] : [$emaildata['CC']]);
			foreach ($cc as $c) {
				$args = self::_parseEmailAddress($c);
				$mail->addCC(...$args);
			}

			$bcc = $autoBCC ? (is_array($autoBCC) ? $autoBCC : [$autoBCC]) : [];
			if (array_key_exists('BCC',$emaildata))
				$bcc = array_merge($bcc, is_array($emaildata['BCC']) ? $emaildata['BCC'] : [$emaildata['BCC']]);
			foreach ($bcc as $b) {
				$args = self::_parseEmailAddress($b);
				$mail->addBCC(...$args);
			}

			if (array_key_exists('SubjectTemplate',$emaildata) &&
				($subjectTemplate = $emaildata['SubjectTemplate'])
			) {
				$render = new \AuntieWarhol\MVCish\View\Render($this);
				if (array_key_exists('TemplateData',$emaildata)) {
					$render->templateData($emaildata['TemplateData']);
				}
				$subject = $render->renderTemplate(
					$render->getEmailTemplateDirectory().$subjectTemplate,false
				);
			}
			elseif (array_key_exists('Subject',$emaildata)) {
				$subject = $emaildata['Subject'];
			}
			if (!isset($subject)) $subject = $defaultSub;
			if ($this->ENV != 'PROD') $subject .= ' ('.$this->ENV.')';
			$mail->Subject = $subject;

			// send HTML & optionally AltBody, or Body
			$useHTML = false;
			if (array_key_exists('HTML',$emaildata) && ($html = $emaildata['HTML'])) {
				$useHTML = true;
				$mail->msgHTML($html);
			}
			elseif (array_key_exists('Template',$emaildata) &&
				($htmlTemplate = $emaildata['Template'])
			) {
				$useHTML = true;
				if (!isset($render)) {
					$render = new \AuntieWarhol\MVCish\View\Render($this);
					if (array_key_exists('TemplateData',$emaildata)) {
						$render->templateData($emaildata['TemplateData']);
					}
				}
				$mail->msgHTML($render->renderTemplate(
					$render->getEmailTemplateDirectory().$htmlTemplate,false
				));
			}

			if ($useHTML) {
				if (array_key_exists('AltBody',$emaildata) && ($text = $emaildata['AltBody'])) {
					$mail->AltBody = $text;
				}
			}
			elseif (array_key_exists('Body',$emaildata) && ($text = $emaildata['Body'])) {
				$mail->Body = $text;
			}
			//$this->log('MVCish')->debug("mail: ".json_encode($mail));

			$rv = $mail->send();
			if ($rv) {
				$this->log('MVCish')->info("Sent Mail To: ".implode('; ',$to)
					.(count($cc)  ? ", CC: " .implode('; ',$cc)  : '')
					.(count($bcc) ? ", BCC: ".implode('; ',$bcc) : '')
					.", Subject: ".$subject);
			}
			else {
				$this->log('MVCish')->error("PHPMailer Error: ".$mail->ErrorInfo);
			}
			return $rv;
	
		}
		// else we return the $mail object and you can do it yourself
		return $mail;
	}

	private static function _parseEmailAddress($str) {
		if (preg_match('/^(.*)? ?<(.*)>$/',$str,$matches)) {
			return [$matches[2],$matches[1]];
		}
		return [$str];
	}

	public function redirect($loc,$status=302) {
		//default: 302 SEE OTHER (appropriate for a typical GET after POST redirect)
		//$this->log('MVCish')->info("redirect $status $loc");
		http_response_code($status);
		header("Location: $loc");
		exit();
	}

	public function cleanInput($val,$default=false,$options=[]) {
		//by default this trims whitespace and strips tags;
		//can turn off strip_tags with an option
		return $this->validator()->cleanData($val,$default,$options);
	}

	public function cleanOutput($val,$options=[]) {
		//by default we html encode special chars, after stripping slashes.
		//if in the future we have fields where we want to allow
		//user submitted html, we can make that an option,
		//which will need to pass $val through a purifier library

		// it's useful to send ENT_NOQUOTES for page title tags,
		// or ENT_COMPAT to encode ' but not " for some attribute values,
		// otherwise encoding quotes is usually a good idea
		$flags = isset($options['FLAGS']) ? $options['FLAGS'] : ENT_QUOTES;

		return htmlspecialchars(stripslashes(isset($val) ? $val : ''),$flags);
	}
}
?>
