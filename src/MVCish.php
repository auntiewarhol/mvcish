<?php
namespace awPHP\MVCish;

use \Monolog\Logger;
use \Monolog\Handler\StreamHandler;
use \PHPMailer\PHPMailer\PHPMailer;
use \Plasticbrain\FlashMessages\FlashMessages;


class MVCish {

	public function __construct(array $options = []) {

		// stash the options and configured environment
		$this->setOptions($options);

		// set Environment Prod/Stage/Local or other
		$this->Environment($this->Options('Environment'));

		// register error handlers
		if (empty($GLOBALS['MVCISH_IGNORE_ERRORS'])) {
			//error_log("using MVCish error_handler");

			set_error_handler(function($errno, $errstr, $errfile, $errline){
				Debug::errorHandler($this,$errno, $errstr, $errfile, $errline);
			},E_ALL);
			register_shutdown_function(function() {
				if ($error = error_get_last()) {
					Debug::errorHandler($this,$error['type'],$error['message'],$error['file'],$error['line']);
				}
			});
		}
		//init PHP session if not command line session
		if (!$this->isCLI()) {
			if (empty(session_id())) { session_start(); }
		}
	}

	// enforce no-dynamic-properties
	public function __set($name, $value) {
		Exception\ServerWarning::throwWarning($this,'Attempt to set undefined property: '
			.static::class.'->'.$name);
	}
	public function __get($name) {
		Exception\ServerWarning::throwWarning($this,'Attempt to read undefined property: '
			.static::class.'->'.$name);
	}

	// ***************************************************************
	// CONFIGURATION *************************

	// OPTIONS -- for configuring MVCish itself 
	// 	(the params you passed in construction)

	private array $_options = [];
	public function Options(string $key=null,$set=null) {
		if (isset($key)) {
			if (isset($set)) $this->_options[$key] = $set;
			return array_key_exists($key,$this->_options) ?
				$this->_options[$key] : null;
		}
		return $this->_options;
	}
	private function setOptions(array $new=null,bool $merge=false):void {
		$this->_options = $merge ? 
			array_replace_recursive($this->_options,$new ?? []) : $new;
	}


	// (DEPLOYMENT) ENVIRONMENT 
	private $_environment;
	public function Environment($new=null): Environment {
		if (!$this->_environment) {
			$new ??= 'Production';
			try {
				$this->_environment =
					Environment\Factory::getEnvironment($this,$new);
			}
			catch(\Exception $e) {
				throw new Exception\ServerError(
					'Unable to instantiate Environment "'.$new.'": '
						. $e->getMessage());
			}
		}
		return $this->_environment;
	}

	// APP CONFIG -- for client app config files, not MVCish configuration
	//	Config can vary by Environment
	private $_appConfig = null;
	public function Config(string $key=null,$set=null) {
		if (!isset($this->_appConfig)) $this->initAppConfig();

		if (isset($key)) {
			if (isset($set)) $this->_appConfig[$key] = $set;
			return array_key_exists($key,$this->_appConfig) ?
				$this->_appConfig[$key] : null;
		}
		return $this->_appConfig;
	}

	// convenience accessor when we want either (Option Priority default)
	public function OptionConfig(string $key,bool $priorityOption=true) {
		return $priorityOption ? 
			($this->Options($key) ?? $this->Config($key)) :
			($this->Config($key)  ?? $this->Options($key));
	}

	private function initAppConfig() {
		//error_log("initAppConfig");

		// if appConfig set in MVCish Options
		if ($appConfig = $this->Options('appConfig')) {
			if ($this->Options('appConfigPriority') == 'OPTION') {
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
		if ($appConfig = $this->Options('appConfig')) {
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
						throw new Exception\ServerError(
							"Failed to parse appConfig from file ".$appConfig
							.': '.$e->getMessage());
					}
				}
			}
		}
		return (empty($result) ? [] : $result);
	}


	// Working Directories *******************************************

	private $usingTempAppDir = false;
	private $appDirectory = null;
	public function getAppDirectory() {
		if (!isset($this->appDirectory)) {
			if ($appDirectory = $this->Options('appDirectory')) {
				$this->appDirectory =
					rtrim($appDirectory,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
			}
			else {
				if (!$this->isCLI()) {
					Exception\ServerWarning::throwWarning($this,
						"Using MVCish without setting an application directory is discouraged; using tmpfiles."
					);
				}
				$this->appDirectory = sys_get_temp_dir().DIRECTORY_SEPARATOR;
				$this->usingTempAppDir = true;
			}
			if (!file_exists($this->appDirectory)) {
				if (!mkdir($this->appDirectory,0755,true)) {
					throw new Exception\ServerError(
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
		// you can set these directly in Options
		// or we will create it in the appDirectory
		
		if ($keyOption = $this->Options($key)) {
			$this->$key =
				rtrim($keyOption,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
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
				throw new Exception\ServerError(
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


	// LOGGING*********************** ***************************

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
							throw new Exception\ServerError("Failed to create logfile");
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

	public function logExceptionMessage(\Throwable $e,string $basemsg=''):void {
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


	// MODEL ************************

		/* 'Model' is only very loosely coupled; just looks for the requested class,
			Can configure to auto-prepend part of a namespace.
			examples:
				$user = $MVCish->Model('\Models\UserQuery'); // returns '\Models\UserQuery'
				$user = $MVCish->Model('UserQuery');         // same, if 'Models\' configured as MODEL_NAMESPACE

			A model_initialize function can be passed in MVCish Options to do any
			setup work needed for the model when MVCish starts.
		*/

	private $modelInited = false;
	private $modelNamespace = null;
	public function initModel() {
		if ($this->modelInited) return true;
		// INIT MODEL if so configured. can come from
		// options or config (options take priority)
		if (
			($mconfig = $this->Options('MODEL')) ||
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

	public function Run($controller=null,$ctrlOptions=[]):bool {

		// update/override Options
		$this->setOptions($ctrlOptions,true);

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


	private ?array $pathArgs = null;
	private ?string $controllerName = null;
	public function controllerName($set=null) {
		if (isset($set)) $this->controllerName = $set;
		return $this->controllerName;
	}
	public function pathArgs($set=null) {
		if (isset($set)) $this->pathArgs = $set;
		return $this->pathArgs;
	}

	private function runController($controller) {
		/*
			we may or may not have a single point of entrance;
			if we do, we figured out from the url what controller you wanted
			and ran it. Otherwise, the url took you directly to a php file as
			usual, and that file ran us, passing the 'controller' as a closure:
		*/
		if ((!empty($controller)) || ($controller = $this->getUrlController())) {

			if (is_callable($controller)) {
				$this->Response(Response::factory($this,$controller($this)));
			}
			elseif (is_string($controller)) {
				$self = $this;
				$this->Response(Response::factory($this,include($controller)));
			}
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
					$this->pathArgs($pathArgs);
					$this->controllerName(substr($fullfile,-(strlen($fullfile) - strlen($ctrlDirectory))));
					return $fullfile;
				}
				//$this->log()->debug("No url controller $fullfile");
			}

			if (!isset($pathinfo['extension'])) $fullfile .= '.php';
			if (substr($fullfile,-4) == '.php') {

				if (file_exists($fullfile)) {
					//$this->log()->debug("Found controller: $fullfile, args=".print_r($pathArgs,true));
					$this->pathArgs($pathArgs);
					$this->controllerName(substr($fullfile,-(strlen($fullfile) - strlen($ctrlDirectory))));
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
					//throw new Exception\MovedPermanently($decoded);
				}
			}
		}
		//$this->log()->warning("no controller found for ".$_SERVER['REQUEST_URI']);
	}

	public function processExceptionResponse(\Throwable $e):bool {
		// if it's our exception (or a subclass of our exceptions),
		// then return exception message unless it's a 500 server error
		if ($e instanceof \awPHP\MVCish\Exception) {
			$code = $e->getCode();
			$this->Response([
				'success' => false,
				'code'       => $e->getCode(),
				"error"      => (($code == Exception::SERVER_ERROR) && !$this->isCLI()) ?
					Exception::serverError : $e->getMessage(),
				'messages'   => ['error' => $e->getMessage()],
				'statusText' => $e->statusText()
			]);
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
							$this->Options('template',null);
							//$this->log('MVCish')->debug('do internal redirect: '.$redirect);
							return $this->Run($controller);
						}
					}
				}
				// otherwise, or if didn't find controller above, do server redirect
				$this->Response()->redirect($redirect);
			}
		}
		// any other/unexpected exceptions return generic server error
		else {
			$this->Response([
				'success' => false,
				'code'     => Exception::SERVER_ERROR,
				"error"    => Exception::serverError,
				'messages' => ['error' => Exception::serverError],
				'statusText' => Exception::serverError
			]);
		}
		return $this->processResponse();
	}


	private $responseMessageTypes = ['info','success','warning','error'];

	private function processResponse():bool {
		if (($beforeRender = $this->Options('beforeRender')) && is_callable($beforeRender)) {
			// if optional beforeRender hook returns false, abort
			if (!$beforeRender($this)) return true;
		}
		// skip if controller flags that it already did the work
		if ($this->Options('rendered')) return true;

		if (!$this->Response()->success()) {
			// render any non-sucess as Error
			return $this->View()->renderError();
		}
		// render the View normally
		return $this->View()->renderView();
	}

	public function processMessages() {
		//array-ify any scalar messages and flash-display/store them
		if ($messages = $this->Response()->messages()) {
			foreach($this->responseMessageTypes AS $mtype) {
				if (isset($messages[$mtype])) {
					if (!is_array($messages[$mtype])) {
						$messages[$mtype] = [$messages[$mtype]];
					}
					foreach($messages[$mtype] AS $m) {
						$this->flashmsg()->$mtype($m);
					}
				}
			}
			$this->Response()->messages($messages);
		}
	}



	// RESPONSE ***************************************************************
	
	private $_response;
	public function Response(Response|array|string $setKey=null,$set=null) {
		// send a Response object or an array and we become a setter
		if (isset($setKey) && !is_string($setKey)) {
			$this->_response = Response::factory($this,$setKey);
		}
		// we will always have a Response, tho the above might replace it
		elseif (!$this->_response) {
			$this->_response = new Response($this);
		}
		// send string key [& optionally $set] and we become convienience method
		if (isset($setKey) && is_string($setKey)) {
			return $this->_response->data($setKey,$set);
		}
		return $this->_response;
	}



	// AUTH *******************************************************************

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

	private function authorize() {
		/* MVCish doesn't know anything about Authentication/Authorization.
			but if you set an object on $MVCish->Auth(), and that object has
			an "Authorize" method, we'll call it, and pass it anything passed
			as an 'Authorize' option. The method should return true if authorized.
			If it returns false, we'll throw an unauthorized exception. Your object
			can also throw its own \awPHP\MVCish\Exception if you want to control
			the messaging (or throw Forbidden instead of Unauthorized, etc)
		*/
		if ($this->Auth() && is_callable([$this->Auth(),'Authorize'])) {
			if (!$this->Auth()->Authorize($this->Options('Authorize'))) {
				throw new Exception\Unauthorized();
			}
		} // else assume authorized
		return true;
	}



	// UTILS / MISC ***********************

	public static function isCLI() {
		return php_sapi_name() == "cli";
	}

	private $_uri;
	public function uri() {
		if (!$this->_uri) {
			$this->_uri = new Util\URI($this);
		}
		return $this->_uri;
	}
	// shortcut alias
	public function uriFor($uri=null,$params=[]) {
		return $this->uri()->uriFor($uri,$params);
	}

	private $_validator;
	public function Validator() {
		if (!$this->_validator) {
			$this->_validator = new Validator($this);
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
				$render = new View\Render($this);
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
					$render = new View\Render($this);
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
