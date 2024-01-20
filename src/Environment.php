<?php
namespace AuntieWarhol\MVCish;
use \Monolog\Formatter\LineFormatter;

class Environment extends \AuntieWarhol\MVCish\Base {

	private string $name;

	private string $defaultAppConfigFilename = 'appConfig.php';

	private array $loggerLevel = [
		'DEFAULT' => 'Warning', 'CLI' => 'Warning'
	];
	private array $lineFormatterParams = [
		'stringFormat'       => null,
		'dateFormat'         => null,
		'allowInlineBreaks'  => false,
		'ignoreEmptyContext' => false,
		'includeStacktraces' => false,
	];

	// logLevel -- define default & for any error code you want special
	//	set to false to indicate error should not be logged
	private string $defaultLogLevel  = 'error';
	private array  $errCodeLogLevels = [
		'401' => 'debug', '301' => 'debug',
		'NONE' => 'error' // same as default but formalized
	];

	private bool $prettyPrintHTML = false;


	//***************************************************************
	//***************************************************************

	public function __construct(\AuntieWarhol\MVCish\MVCish $MVCish) {
		parent::__construct($MVCish);

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

	public function __toString(): string  { return $this->name(); }

	public function name():string {
		if (!isset($this->name)) {
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


	public function getDefaultAppConfigFilename() {
		return $this->defaultAppConfigFilename;
	}

	private string $appConfigFile;
	public function appConfigFile() {

		// option or default

		// if you want to specify a full FilePath to appConfig in MVCish options, use
		// ['appConfig' => $filepathname], and MVCish will handle it before calling
		// the Environment. Whereas use 'appConfigFilename' to tell us the name of the
		// file we should expect to find in the application config directory
		// (which will have been separately optioned as 'appConfigDirectory' or defaulted),
		// which may or may not also include $filename-$environmentName variant(s)

		if (!isset($this->appConfigFile)) {

			if (empty($this->MVCish->options['appConfigFilename'])) {
				$acFilename = $this->getDefaultAppConfigFilename();
			}
			else {
				$acFilename = $MVCish->options['appConfigFilename'];
			}

			if ((!$this->isRootClass()) &&
				(($name = $this->name()) && !empty($name)) &&
				($pi = pathinfo($acFilename))
			) {
				$acFilename = $pi['filename'] . '-'.$name . '.' . $pi['extension'];
			}
			$this->appConfigFile = $this->MVCish()->getAppConfigDirectory().$acFilename;
		}
		return $this->appConfigFile;
	}


	public function processAppConfigFile():array {

		$appConfigFilePath = $this->appConfigFile();
		//error_log("processAppConfigFile ".$appConfigFilePath);

		// return empty array if we're looking for a defaulted file,
		// throw error if looking for a file the client configured.
		if (!file_exists($appConfigFilePath)) {
			//error_log("notfound appConfig File $appConfigFilePath");
			if ($this->isRootClass() && isset($MVCish->options['appConfigFilename'])) {
				throw new \AuntieWarhol\MVCish\Exception\ServerError(
					"Could not find appConfig file: ".$appConfigFilePath);
			}
			return [];
		}

		$result = [];
		try {
			error_log("processing appConfig File $appConfigFilePath");
			$result = include($appConfigFilePath);
		}
		catch(\Throwable $e) {
			throw new \AuntieWarhol\MVCish\Exception\ServerError(
				"Failed to parse appConfig from file ".$appConfigFilePath
				.': '.$e->getMessage());
		}
		return empty($result) ? [] : $result;
	}

	// recursively process appConfig files down to the current evironment
	public function getAppConfig():array {

		// we are in this root class
		if ($this->isRootClass()) {
			return $this->processAppConfigFile();
		}
		// we are in a child class
		else{
			return array_replace(
				$this->parentObject()->getAppConfig(),
				$this->processAppConfigFile());
		}
	}


	// ***************************************************************
	// Logging *******************************************************

	public function getLoggerLevel():string {
		return $this->loggerLevel[
			($this->MVCish()->isCLI() && isset($this->loggerLevel['CLI'])) ? 'CLI' :
			'DEFAULT'] ?? 'Debug';
	}

	public function getLineFormatter(): \Monolog\Formatter\LineFormatter {
		// (?string $format = null, ?string $dateFormat = null, 
		// 	bool $allowInlineLineBreaks = false, bool $ignoreEmptyContextAndExtra = false, 
		//	bool $includeStacktraces = false)
		return new LineFormatter(
			$this->lineFormatterParams['stringFormat']       ?? null,
			$this->lineFormatterParams['dateFormat']         ?? null,
			$this->lineFormatterParams['allowInlineBreaks']  ?? false,
			$this->lineFormatterParams['ignoreEmptyContext'] ?? false,
			$this->lineFormatterParams['includeStacktraces'] ?? false
		);
	}

	// use $nullcode as a stand-in for errCode in LogLevel and buildMessage functions
	private string $nullcode = 'NONE';
	public function getNullCode():string { return $this->nullcode; }

	// logLevel -- define default & for any error code you want special
	//	set to false to indicate error should not be logged
	public function getErrCodeLogLevel($errCode):string {
		$errCode ?? $this->getNullCode();
		if (array_key_exists($errCode,$this->errCodeLogLevels))
			return $this->errCodeLogLevels[$errCode];
	}

	private array $messageBuilders = [];
	public function messageBuilder(string $errCode,callable $new=null):callable {
		if (isset($new)) {
			$this->messageBuilders[$errCode] = $new;
		}
		if (array_key_exists($errCode,$this->messageBuilders)) {
			return $this->messageBuilders[$errCode];
		}
	}
	public function buildDefaultExceptionMessage(Throwable $e,string $basemsg):string {
		$code = $e->getCode()    ?: '';
		$msg  = $e->getMessage() ?: '';
		return $basemsg.' '
			.(empty($code) ? '' : $code.': ')
			.$msg .' ('.$e->getFile().': '.$e->getLine().')';
	}

	public function buildExceptionMessage(Throwable $e,string $basemsg):string {
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
	public function buildNotDeliverableMessage(Throwable $e,string $basemsg):string {
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
	public function prettyPrintHTML(bool $new=null):bool {
		if (isset($new)) $this->prettyPrintHTML = $new;
		return $this->prettyPrintHTML;
	}

}
?>
