<?php
namespace AuntieWarhol\MVCish;
use \Monolog\Formatter\LineFormatter;

class Environment {


	private $MVCish;
	public function __construct(\AuntieWarhol\MVCish\MVCish $MVCish) {
		$this->MVCish = $MVCish;

		// set messageBuilder functions for the Not Deliverable codes
		$notDeliverable = function ($e,$basemsg):string {
			return $this->buildNotDeliverableMessage($e,$basemsg);
		};
		foreach(['301','401','404'] as $errCode) {
			$this->messageBuilder($errCode,$notDeliverable);
		}
		// and for special case where exception doesn't have a code
		$this->messageBuilder($this->getNullCode(),function($e,$basemsg):string {
			return $this->buildExceptionMessage($e,$basemsg)
				.(isset($_SERVER['REQUEST_URI'])  ? ' ('.$_SERVER['REQUEST_URI'].')'  : '')
				.(isset($_SERVER['HTTP_REFERER']) ? ' ('.$_SERVER['HTTP_REFERER'].')' : '');
			}
		);
	}
	function __destruct() { unset($this->MVCish); }
	private function MVCish(): \AuntieWarhol\MVCish\MVCish { return $this->MVCish; }


	public string $name = '';
	public function __toString(): string  { return $this->name(); }

	public function name():string {
		if (empty($this->name)) {
			if ($reflect = new \ReflectionClass($this)) {
				$this->name = $reflect->getShortName();
			}
		}
		return $this->name;
	}


	// ***************************************************************
	// Config Files **************************************************

	// this is for client application config data for its own needs,
	// not for configuration of MVCish.


	public function getAppConfigDirectory() {
		return $this->MVCish()->getAppDirectory().'config';
	}

	// process the application config file and return it as an array
	public string $defaultAppConfigFilename = 'appConfig.php';
	public function getDefaultAppConfigFilename() { return $this->defaultAppConfigFilename; }


	public function processAppConfigFile($acFilename,$ignoreMissing=false):mixed {
		$appConfigFilePath = $MVCish->getAppConfigDirectory().$acFilename;

		// return empty array if told to ignore missing file, 
		// else return false to indicate that file was missing.
		if (!file_exists($appConfigFilePath)) {
			error_log("notfound appConfig File $appConfigFilePath");
			return $ignoreMissing ? [] : false;
		}

		$result = null;
		try {
			error_log("processing appConfig File $appConfigFilePath");
			$result = include($appConfigFilePath);
		}
		catch(\Throwable $e) {
			throw new \AuntieWarhol\MVCish\Exception\ServerError(
				"Failed to parse appConfig from file ".$appConfigFilePath
				.': '.$e->getMessage();
			);
		}
		return empty($result) ? [] : $result;
	}
 
	public function getAppConfigRoot():array {
		$MVCish = $this->MVCish();

		// option or default
		$usingDefault = false;

		// if you want to specify a full FilePath to appConfig in MVCish options,
		// use ['appConfig' => $filepathname], and MVCish will handle it before
		// calling us. Whereas use 'appConfigFilename' to tell us the name of the
		// file we should expect to find in the application config directory
		// (which will have been separately optioned as 'appConfigDirectory' or defaulted),
		// which may or may not also includee $filename-$environmentName variant 
		// versions to be sucked in by Environment subclasses.

		if (empty($MVCish->options['appConfigFilename'])) {
			$acFilename = $this->getDefaultAppConfigFilename();
		}
		else {
			$usingDefault = true;
			$acFilename = $MVCish->options['appConfigFilename'];
		}
		$result = $this->processAppConfigFile($acFilename,$usingDefault);
		if ($result === false) {
			// option-configured file not found, throw error
			// if using default, there just might not be one and that's ok.
			throw new \AuntieWarhol\MVCish\Exception\ServerError(
				"Could not find appConfig file: ".$appConfigFilePath
		}
		return empty($result) ? [] : $result;
	}

	// recursively process appConfig files down to the current evironment
	public function getAppConfig():array {
		// If we are in a child class
		if ((bool)class_parents($this)) {
			return array_replace(parent::getAppConfig(),
				$this->processAppConfigFile(
					$this->getDefaultAppConfigFilename(),true
				));
		}
		else {
			return $this->getAppConfigRoot();
		}
	}


	// ***************************************************************
	// Logging *******************************************************

	public array $loggerLevel = [
		'DEFAULT' => 'Error', 'CLI' => 'Debug'
	];
	public function getLoggerLevel():string {
		return $this->loggerLevel[($this->MVCish()->isCLI() &&
			isset($this->loggerLevel['CLI'])) ? 'CLI' : 'DEFAULT'];
	}

	public array $lineFormatterParams = [
		'stringFormat'       => null,
		'dateFormat'         => null,
		'allowInlineBreaks'  => false,
		'ignoreEmptyContext' => false,
		'includeStacktraces' => false,
	];

	public function getLineFormatter(): \Monolog\Formatter\LineFormatter {
		// (?string $format = null, ?string $dateFormat = null, 
		// 	bool $allowInlineLineBreaks = false, bool $ignoreEmptyContextAndExtra = false, 
		//	bool $includeStacktraces = false)
		return new LineFormatter(
			$this->lineFormatterParams['stringFormat']       ?= null,
			$this->lineFormatterParams['dateFormat']         ?= null,
			$this->lineFormatterParams['allowInlineBreaks']  ?= false,
			$this->lineFormatterParams['ignoreEmptyContext'] ?= false,
			$this->lineFormatterParams['includeStacktraces'] ?= false
		);
	}

	// use $nullcode as a stand-in for errCode in LogLevel and buildMessage functions
	public string $nullcode = 'NONE';
	public function getNullCode():string { return $this->nullcode; }

	// logLevel -- define default & for any error code you want special
	//	set to false to indicate error should not be logged
	public string $defaultLogLevel = 'error';
	public array  $errCodeLogLevels = [
		'401' => 'debug', '301' => 'debug',
		self::$nullcode => 'error' // same as default but wanted to formalize
	];
	public function getErrCodeLogLevel($errCode):string {
		$errCode ?= $this->getNullCode();
		if (array_key_exists($this->errCodeLogLevels,$errCode))
			return $this->errCodeLogLevels[$errCode];
	}

	private array $messageBuilders = [];
	public messageBuider($errCode,$new=null):callable {
		if (isset($new)) {
			$this->messageBuilders[$errCode] = $new;
		}
		if (array_key_exists($errCode,$this->messageBuilders)) {
			return $this->messageBuilders[$errCode];
		}
	}
	public function buildDefaultExceptionMessage($e,$basemsg):string {
		$code = $e->getCode()    ?: '';
		$msg  = $e->getMessage() ?: '';
		return $basemsg.' '
			.(empty($code) ? '' : $code.': ')
			.$msg .' ('.$e->getFile().': '.$e->getLine().')';
	}

	public function buildExceptionMessage($e,$basemsg):string {
		// if there's a dedicated builder for the errCode, use that
		if (($code = $e->getCode()) && ($builder = $this->messageBuilder($code))) {
			return $builder($e,$basemsg);
		}
		// else default
		return $this->buildDefaultExceptionMessage($e,$basemsg);
	}

	// helper function to build a separate message for codes deemed
	// "undeliverable" instead of "broken", eg 404s, etc.
	// Not called directly, but we call from the builder functions
	// defined above for those codes. Of course subclasses can override.
	public function buildNotDeliverableMessage($e,$basemsg):string {
		// ignore basemsg & getMessage
		$code = $e->getCode()    ?: '';
		return "$code: ".$_SERVER['REQUEST_URI']
			.(isset($_SERVER['HTTP_REFERER']) ?
				' (ref: '.$_SERVER['HTTP_REFERER'].')' : '');
	}



	// ***************************************************************
	// Email *********************************************************

	public function processEmailSubjectLine(string $subject):string {
		return $subject;
	}

	// ***************************************************************
	// Rendering HTML*************************************************

	// Render does the actual rendering, but we can flag whether or not
	// to pretty-print the html so view-source is comprehensible
	public string $prettyPrintHTML = false;
	public function prettyPrintHTML($new=null) {
		if (isset($new)) $this->prettyPrintHTML = $new;
		return $this->prettyPrintHTML;
	}

}
?>
