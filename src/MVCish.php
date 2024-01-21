<?php
namespace AuntieWarhol\MVCish;

use \Monolog\Logger;
use \Monolog\Handler\StreamHandler;
use \PHPMailer\PHPMailer\PHPMailer;
use \Plasticbrain\FlashMessages\FlashMessages;


class MVCish {

	public $options = [];

	// CONSTRUCT

	public function __construct(array $options = []) {

		// stash the options and configured environment
		$this->options = $options;

		// Prod/Stage/Local or other
		$this->Environment(
			isset($this->options['Environment']) ? $this->options['Environment'] : null
		);

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
		if (!$this->isCLI()) {
			if (empty(session_id())) { session_start(); }
		}
	}

	private function _error_handler($errno, $errstr, $errfile, $errline) {
		//error_log("error_handler ".$this->translatePHPerrCode($errno).' '.$errstr.' '.$errfile.' '.$errline);
		// ignore warnings when @ error suppression operator used
		$er = error_reporting();
		if ($er == 0 || $er == 4437) return true; //4437=php8 hack

		// hack to ignore this warning, because the only way to test if something is serialized
		// is to try and unserialize it. Maybe that should use suppression operator tho?
		if (($errno == E_NOTICE) && (substr($errstr,0,11) == 'unserialize')) return true;

		$logged = false;
		$exception = null; $messages = [];

		try {
			// hacky but not sure how else to do it: if you know you're triggering us with an 
			// exception deliberately, set handlingException so we'll use the one you already have
			// (see "Exception\ServerWarning-trigger()")
			if (isset($GLOBALS['MVCish_handlingException'])) {
				$exception = $GLOBALS['MVCish_handlingException'];
				$GLOBALS['MVCish_handlineException'] = null;
			}
			else {
				$exception = \AuntieWarhol\MVCish\Exception::handlerFactory(
					$this, $errno, $errstr, $errfile, $errline
				);
			}
			$this->logExceptionMessage($exception);
			$logged = true;
		}
		catch(\Throwable $e) {
			$messages[] = "Error creating MVCish\Exception: ".$e->getMessage();

			// old fashioned way. just in case
			$logged = false;
			try {
				if ($this->isFatalPHPerrCode($errno)) {
					$exception = new \Exception($errstr);
					$this->logExceptionMessage($exception);
					$logged = true;
				}
			}
			catch(\Throwable $e) {
				$messages[] = "Error creating generic Exception: ".$e->getMessage();
			}
		}
		if (!$logged) { // old fashioned way if all else failed
			$messages = array_merge(
				$this->_buildErrorMessages($errno, $errstr, $errfile, $errline),
				$messages
			);
			$msgMethod = $this->isFatalPHPerrCode($errno) ? 'error' : 'warning';
			try {
				foreach ($messages as $m) {
					$this->log('MVCish')->$msgMethod($m);
				}
			} catch (\Throwable $e) {
				$msg[] = "Additional error encountered writing to MVCish log: ".$e->getMessage();
				foreach ($msg as $m) { error_log($m); }
			}
		}

		if ($this->isFatalPHPerrCode($errno)) {
			if (isset($exception)) $this->processExceptionResponse($exception);
			exit(1);
		}
		return true;
	}

	// usually let Environment and Exception take care of this, but for catastrophic
	// failures where we can't get one or both of those, here's the dumb way
	private function _buildErrorMessages($errno, $errstr, $errfile, $errline):array {

		$isMVCishWarning = false;
		$errstr = \AuntieWarhol\MVCish\MVCish::cleanMVCishWarning($errno,$errstr,$isMVCishWarning);

		//hacky, but...
		if ($isMVCishWarning) {
			$errConst = 'E_MVCISH_WARNING';
		}
		else {
			$errConst = $this->translatePHPerrCode($errno);
		}

		$messages = [];
		$messages[] = $errConst.": $errstr"
			.(($errConst != 'E_MVCISH_WARNING') ? "; line $errline:$errfile" : '');

		if ($this->isFatalPHPerrCode($errno)) {
			$messages[] = "TRACE: ".$this->getCallerInfo();
		}
		return $messages;
	}

	public static function cleanMVCishWarning(int $errno, string $errstr,bool &$wasCleaned=false):string {
		if (($errno == E_USER_WARNING) && (substr($errstr,0,18) == 'E_MVCISH_WARNING: ')) {
			$wasCleaned = true;
			return substr($errstr,18);
		}
		return $errstr;
	}

	// CONFIG *************************

	private $_environment;
	public function Environment($new=null): \AuntieWarhol\MVCish\Environment {
		if (!$this->_environment) {
			$new ??= 'Production';
			try {
				$this->_environment =
					\AuntieWarhol\MVCish\Environment\Factory::getEnvironment($this,$new);
			}
			catch(\Exception $e) {
				throw new \AuntieWarhol\MVCish\Exception\ServerError(
					'Unable to instantiate Environment "'.$new.'": '
						. $e->getMessage());
			}
		}
		return $this->_environment;
	}

	private $_appConfig = null;
	private function initAppConfig() {
		//error_log("initAppConfig");

		// if appConfig set in MVCish Options
		if (isset($this->options['appConfig']) && 
			($appConfig = $this->options['appConfig'])
		) {
			if (isset($this->options['appConfigPriority']) &&
				($this->options['appConfigPriority'] == 'OPTION')
			) {
				// Option replaces Environment
				$this->_appConfig = array_replace(
					$this->Environment()->getAppConfig(),
					$this->_getOptionAppConfig()
				);
			}
			else {
				// Environment replaces Option
				$this->_appConfig = array_replace(
					$this->_getOptionAppConfig(),
					$this->Environment()->getAppConfig()
				);
			}
		}
		else {
			// only comes from Environment
			$this->_appConfig = $this->Environment()->getAppConfig();
		}
		//$this->log('MVCish')->debug("MVCish appConfig",$this->_appConfig);
		//error_log("MVCish appConfig= ".print_r($this->_appConfig,true));
	}

	private function _getOptionAppConfig():array {
		$result = null;
		// if appConfig set in MVCish Options
		if (isset($this->options['appConfig']) && 
			($appConfig = $this->options['appConfig'])
		) {
			if (is_array($appConfig)) {
				// appConfig has just been passed in as an array
				return $appConfig;
			}
			else if (is_string($appConfig)) {
				// we have been given an config filename
				if (file_exists($appConfig)) {
					try {
						$result = include($appConfig);
					}
					catch(\Throwable $e) {
						throw new \AuntieWarhol\MVCish\Exception\ServerError(
							"Failed to parse appConfig from file ".$appConfig
							.': '.$e->getMessage());
					}
				}
			}
		}
		return (empty($result) ? [] : $result);
	}

	public function Config($key=null) {
		if (!isset($this->_appConfig)) {
			$this->initAppConfig();
		}
		if (!isset($key)) {
			return $this->_appConfig;
		}
		if (array_key_exists($key,$this->_appConfig)) {
			return $this->_appConfig[$key];
		}
		return;
	}


	private $usingTempAppDir = false;
	private $appDirectory = null;
	public function getAppDirectory() {
		if (!isset($this->appDirectory)) {
			if (empty($this->options['appDirectory'])) {
				if (!$this->isCLI()) {
					$this->throwWarning(
						"Using MVCish without setting an application directory is discouraged; using tmpfiles."
					);
				}
				$this->appDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR;
				$this->usingTempAppDir = true;
			}
			else {
				$this->appDirectory =
					rtrim($this->options['appDirectory'],DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
			}

			if (!file_exists($this->appDirectory)) {
				if (!mkdir($this->appDirectory,0755,true)) {
					throw new \AuntieWarhol\MVCish\Exception\ServerError(
						"Failed to find or create App Directory: ".$this->appDirectory);
				}
			}
		}
		return $this->appDirectory;
	}
	public function usingTempAppDir():bool {
		return $this->usingTempAppDir;
	}

	private function _findOrCreateChildDirectory(string $parentDir, string $name,string $key,string $configKey=null):void {
		// you can set these directly in options
		// or we will create it in the appDirectory
		
		if (isset($this->options[$key])) {
			$this->$key =
				rtrim($this->options[$key],DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
		}
		else if (isset($configKey) && ($configVal = $this->Config($configKey))) {
			$this->$key =
				rtrim($configVal,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
		}
		else {
			$this->$key = $parentDir.$name.DIRECTORY_SEPARATOR;
		}

		if (!file_exists($this->$key)) {
			if (!mkdir($this->$key,0755,true)) {
				throw new \AuntieWarhol\MVCish\Exception\ServerError(
					"Failed to find or create $name directory: ".$this->$key);
			}
		}
	}

	// option or default
	private $runtimeDirectory = null;
	public function getRuntimeDirectory():?string {
		if (!isset($this->runtimeDirectory)) {
			$this->_findOrCreateChildDirectory(
				$this->getAppDirectory(),'runtime','runtimeDirectory');
		}
		return $this->runtimeDirectory;
	}

	// option or default
	private $appConfigDirectory = null;
	public function getAppConfigDirectory():?string {
		if (!isset($this->appConfigDirectory)) {
			$this->_findOrCreateChildDirectory(
				$this->getAppDirectory(),'config','appConfigDirectory');
		}
		return $this->appConfigDirectory;
	}

	// option or default
	private $logDirectory = null;
	public function getLogDirectory():?string {
		if (!isset($this->logDirectory)) {
			$this->_findOrCreateChildDirectory(
				$this->getRuntimeDirectory(),'logs','logDirectory');
		}
		return $this->logDirectory;
	}


	// option or appConfig or default
	private $templateDirectory = null;
	public function getTemplateDirectory($reset = false):?string {
		if ($reset || !isset($this->templateDirectory)) {
			$this->_findOrCreateChildDirectory(
				$this->getAppDirectory(),'templates','templateDirectory','TEMPLATE_DIRECTORY');
		}
		return $this->templateDirectory;
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

	public function Run($controller=null,$options=[]):bool {
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

	private function logExceptionMessage(\Throwable $e,string $basemsg=''):void {
		//error_log("logging ".$e->getMessage());
		try {
			$environment = $this->Environment();
			if ($logLevel = $environment->getErrCodeLogLevel($e->getCode())) {
				$msg = $environment->buildExceptionMessage($e,$basemsg);
				$this->log('MVCish')->$logLevel($msg);
			}
		}
		catch(\Throwable $e) {
			error_log("Fatal error writing to error logs: ".$e->getMessage());
			exit(1);
		}
		//error_log("exiting logException");
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

	private function processExceptionResponse(\Throwable $e):bool {
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

	private function processResponse():bool {
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

	public static function throwWarning(string $message,string $file=null, int $line=null):void {
		$w = \AuntieWarhol\MVCish\Exception\ServerWarning::create($message,null,null,$file,$line);
		$w->trigger();
	}

	public static function isCLI() {
		return php_sapi_name() == "cli";
	}

	public static function getCallerInfo(int $max=0,array|\Throwable $trace=null):string {
		if (is_object($trace) && method_exists($trace,'getFilteredTrace')) {
			$trace = $trace->getFilteredTrace();
		}
		return implode('; ',self::getCallerInfoStrings($max,$trace));
	}

	public static function getCallerInfoStrings(int $max=0,array $trace=null):array {
		if (is_object($trace)) {
			$trace = self::getRelevantCallers($max,$trace);
		}
		$trace ??= self::getRelevantCallers($max);

		$strings = [];
		foreach($trace as $t) {
			foreach(['file','class'] as $k) { $t[$k] ??= ''; }
			$strings[] = 
				(empty($t['file'])     ? '' : basename($t['file']).': ').
				(empty($t['class'])    ? '' : $t['class'].'->').
				(empty($t['function']) ? '' : 
					$t['function'].'('.	(empty($t['args']) ? '' :
						implode(',',
							array_map(function($v) {
								if (is_object($v) && method_exists($v,'__toString')) {
									$v = $v->__toString();
								}
								return 	is_string($v) ? ('"'.(strlen($v) > 7 ? substr($v,0,7).'...' : $v).'"') :
										(is_object($v) ? '$'.get_class($v) : strtoupper(gettype($v)));
							},$t['args'])
						)).')');
		}
		return $strings;
	}

	public static function getRelevantCallers(int $max=0,\Throwable $forException=null):array {
		$trace = isset($forException)? $forException->getTrace() : debug_backtrace();
		array_shift($trace); // pop this call

		// try to skip all the stuff what likely went into outputting the error

		$ignoreUntil = null;
		if (isset($forException)) {
			$ignoreUntil = ['file' => $forException->getFile(), 'line' => $forException->getLine()];
			//error_log("IE= ".$forException->getFile().' '.$forException->getLine());
		}

		foreach ($trace as $i => $t) {
			$skips = [];
			if (
				(isset($ignoreUntil) && !(isset($t['file']) && isset($t['line']) &&
					($t['file'] == $ignoreUntil['file']) && ($t['file'] == $ignoreUntil['file']))) ||

				(isset($t['class']) && (($t['class'] == 'Exception') ||
					is_subclass_of($t['class'],'Exception'))) ||

				(isset($t['file']) && str_contains($t['file'],'mvcish/src/Exception')) ||

				(((isset($t['class']) && ($t['class'] == 'AuntieWarhol\MVCish\MVCish')) || 
				 (isset($t['file'])  && ($t['file'] == __FILE__))) &&
				in_array($t['function'],['logExceptionMessage','_error_handler','trigger_error',
					'throwWarning','getCallerInfo','getCallerInfoStrings',
					'AuntieWarhol\MVCish\{closure}'])) ||
				
				((isset($t['class']) && (($t['class'] == 'AuntieWarhol\MVCish\Environment') ||
				  is_subclass_of($t['class'],'AuntieWarhol\MVCish\Environment'))) &&
				in_array($t['function'],['buildDefaultExceptionMessage','buildExceptionMessage']))
			) {
				$count = count($trace);
				$skips[] = $trace[$i]; unset($trace[$i]);
				//error_log('skipping '.$t['function'].' trace was '.$count.' now '.count($trace));
			}
			else {
				//error_log('keeping '.($t['file'].' ' ?? '').($t['class'] ?? '').'->'.$t['function'].' trace is '.count($trace));
				unset($ignoreUntil);
				break; //once we find a keeper, keep the rest
			}
		}
		// just in case we emptied it out
		if (empty($trace) && !empty($skips)) { $trace = $skips; }

		return ($max > 0) ? array_slice($trace,0,$max) : $trace;
	}

	public static function translatePHPerrCode($errno) {
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
	public static function isFatalPHPerrCode($errno) {
		return in_array($errno,[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR]);
	}


	private $_logfile;
	private $_logs = [];
	public function log($channel = null) {

		$name = $this->Config('APPLICATION_NAME') ?	$this->Config('APPLICATION_NAME') : 'MVCish';
		if (!$channel) $channel = $name;

		if (!array_key_exists($channel,$this->_logs)) {

			if (!$this->_logfile) {
				if ($this->usingTempAppDir()) {
					$this->_logfile = 'php://stdout';
				}
				else {

					$logConfig = $this->Config('LOGFILE');
					$logfile = $this->getLogDirectory()
						.(isset($logConfig) ? $logConfig : $name.'.log');

					if (!file_exists($logfile)) {
						if (!touch($logfile))
							throw new \AuntieWarhol\MVCish\Exception\ServerError("Failed to create logfile");
					}
					$this->_logfile = $logfile;
				}
				//error_log("Writing log to ".$this->_logfile);
			}

			$logger      = new Logger($channel);
			$handler = new StreamHandler($this->_logfile,
				constant('Monolog\Logger::'.strtoupper($this->Environment()->getLoggerLevel()))
			);
			if ($formatter = $this->Environment()->getLineFormatter()) {
				$handler->setFormatter($formatter);
			}
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

			// hook to allow environment to modify subject (add '(DEV)' or whatever)
			$subject = $this->Environment()->processEmailSubjectLine($subject);
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
