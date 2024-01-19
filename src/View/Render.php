<?php
namespace AuntieWarhol\MVCish\View;


class Render {

	private $MVCish;
	public function __construct(\AuntieWarhol\MVCish\MVCish $MVCish) {
		$this->MVCish = $MVCish;
	}
	function __destruct() { unset($this->MVCish); }
	private function MVCish(): \AuntieWarhol\MVCish\MVCish { return $this->MVCish; }

	// set/get data (besides controller Response) to be used by templates,
	// eg nav/menu options, page titles, js & css links, etc
	private $templateData = [];
	final public function templateData($key=null,$value=null,$opt='') {
		// ignore value without key
		if (!isset($key)) {
			// pass nothing to get the whole array
			return $this->templateData;
		}
		// passed key
		if (is_array($key)) {
			// if array key, replace & return the whole array. ignore value.
			$this->templateData = $key;
			return $this->templateData;
		}
		if (is_string($key)) {
			// if string key..

			if (isset($value)) {
				if (strpos($opt,'array_') !== false) {

					//array opts
					$combinedValue = isset($this->templateData[$key]) ? $this->templateData[$key] : [];
					if (!is_array($combinedValue)) $combinedValue = [$combinedValue];

					switch ($opt) {
						case 'array_push':
							array_push($combinedValue,$value); break;
						case 'array_unshift':
							array_unshift($combinedValue,$value); break;
						case 'array_merge':
							$combinedValue = array_merge($combinedValue,$value); break;
						case 'array_merge_unshift':
							$combinedValue = array_merge($value,$combinedValue); break;
						case 'array_replace_recursive':
							$combinedValue = array_replace_recursive($combinedValue,$value); break;
						case 'array_merge_recursive':
							$combinedValue = array_merge_recursive($combinedValue,$value); break;
						default:
							throw new \AuntieWarhol\MVCish\Exception\ServerError('Invalid opt "'.$opt.'"');
					}
					$value = $combinedValue;
				}
				// set/replace value for key
				$this->templateData[$key] = $value;
			}
			elseif ($opt == 'setNull') {
				// set the key to null
				$this->templateData[$key] = null;
			}
			// return value for key
			if (isset($this->templateData[$key]))
				return $this->templateData[$key];
			return null;
		}
		throw new \AuntieWarhol\MVCish\Exception\ServerError('Invalid Key');
	}

	// make the html generator available to templates 
	private $html;
	final public function html() {
		if (!isset($this->html)) {
			$this->html = new Render\HTML();
		}
		return $this->html;
	}

	// alias MVCish methods templates will commonly need
	final public function Environment() {
		return $this->MVCish()->Environment();
	}
	final public function Config($k) {
		return $this->MVCish()->Config($k);
	}
	final public function Options() {
		return $this->MVCish()->options;
	}
	final public function Response() {
		return $this->MVCish()->Response;
	}
	final public function Auth() {
		return $this->MVCish()->Auth();
	}
	final public function Model($m) {
		return $this->MVCish()->Model($m);
	}
	final public function cleanOutput($str,$opt=[]) {
		return $this->MVCish()->cleanOutput($str,$opt);
	}
	final public function uri() {
		return $this->MVCish()->uri();
	}
	final public function uriFor($uri=null,$params=[]) {
		return $this->MVCish()->uri()->uriFor($uri,$params);
	}
	final public function assetUriFor($uri,$params=[],$opts=[]) {
		return $this->MVCish()->uri()->assetUriFor($uri,$params,$opts);
	}


	//*****************************
	// find and render the template
	//
	final public function renderTemplate($fullPathTemplateFile=null,$output=true) {
		$html = '';
		$templateFile = null;
		$MVCish = $this->MVCish();
		if (isset($fullPathTemplateFile) ||
			(isset($MVCish->options['template']) && ($templateFile =
				$MVCish->options['template'])) ||
			($fullPathTemplateFile = $this->getDefaultTemplateName($this->getControllerTemplateDirectory()))
		) {
			if (!isset($fullPathTemplateFile)) 
				$fullPathTemplateFile = $this->getValidFullPathToFile($templateFile,
					$this->getControllerTemplateDirectory());

			// call hook
			$this->beforeRenderControllerTemplate();

			// render the controller template html
			$html = $this->renderFile($fullPathTemplateFile);


			// if the helper defines a master template
			if ($masterTemplateFile = $this->getMasterTemplateFile()) {

				$fullPathMasterTemplateFile = $this->getValidFullPathToFile($masterTemplateFile,
					$this->getMasterTemplateDirectory());

				// call hook; hook may or may not return modified replacement html
				// (it can also modify in-place by receiving a reference)
				if ($newHtml = $this->beforeRenderMasterTemplate($html)) {
					$html = $newHtml;
				}

				// render the master template html
				$html = $this->renderFile($fullPathMasterTemplateFile);
			}
		}
		elseif ($noTemplate = $MVCish->Config('noTemplateException')) {
			if (is_string($noTemplate) && class_exists($noTemplate)) {
				throw new $noTemplate();
			}
			// send bool true to use default 404
			else {
				throw new \AuntieWarhol\MVCish\Exception\NotFound();
			}
		}

		// Output
		if ($output) {
			if ($MVCish->Environment()->prettyPrintHTML()) {
				$clean = \AuntieWarhol\MVCish\Util\HtmLawed::call(
					'hl_tidy',[$html, 't', 'span']) ?? '';
				echo substr($clean, strpos($clean, "\n")+1);
			}
			else {
				echo $html;
			}
		}
		else {
			return $html;
		}
	}

	final public function getValidFullPathToFile($templateFile,$templateDirectory=null) {
		$MVCish = $this->MVCish();

		// if begins with /, consider it absolute from document root
		if (substr($templateFile,0,1) == DIRECTORY_SEPARATOR) {
			$templateDirectory = $_SERVER['DOCUMENT_ROOT'];
		}
		// else it's relative from passed templateDirectory
		elseif (isset($templateDirectory)) {

			// remove any ending slash
			$templateDirectory = rtrim($templateDirectory,DIRECTORY_SEPARATOR);
			if (!is_dir($templateDirectory)) {
				$MVCish->log('MVCish')->error("Can't find templateDirectory: $templateDirectory");
				throw new \AuntieWarhol\MVCish\Exception\ServerError('Template Directory Not A Directory');
			}
		}
		else {
			throw new \AuntieWarhol\MVCish\Exception\ServerError('Template Directory Not Defined');
		}

		$fullPathTemplateFile = $templateDirectory.DIRECTORY_SEPARATOR.$templateFile;

		if (!is_file($fullPathTemplateFile)) {
			$MVCish->log('MVCish')->error("Can't find templateFile: $fullPathTemplateFile");
			if (($noTemplate = $MVCish->Config('noTemplateException')) &&
				(is_string($noTemplate) && class_exists($noTemplate))) {
					throw new $noTemplate();
			}
			else {
				throw new \AuntieWarhol\MVCish\Exception\NotFound();
			}
		}
		return $fullPathTemplateFile;
	}


	final public function renderFile($templateFile) {
		// $this|$self (Render) and $templateFile will be available inside the template file
		$self = $this;
		ob_start();
		include($templateFile);
		return ob_get_clean();
	}

	// return $dir with guaranteed trailing /
	final public function cleanDirectoryName($dir) {
		return rtrim($dir,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
	}

	final public function getRootTemplateDirectory() {
		return $this->MVCish()->getTemplateDirectory();
	}

	// ***************************************************************************
	// subclass interface
	//
	// The client application can use (pre-loaded or autoloadable) subclasses 
	// to override any of the below. This allows the application to create
	// a renderClass that prepares template data and/or provides a master template.
	// App can have a subclass for each site section where these things might be
	// different (home pages vs account pages vs admin pages, etc).
	// The class is provided via MVCish->options, so if there's just one, it
	// could be provided in app-config.php, or if there are multiple, then
	// a controller can set the one it uses in Run options.
	// The class may be defined like:
	//
	//	   namespace \MyApp\Render;
	//	   class Account extends \AuntieWarhol\MVCish\View\Render {
	//	      ...override/add methods as needed to render an Account template
	//	   }
	//
	//  and then Controller uses like:
	//     $MVCish->Run(function($self){
	//        ...my controller code
	//     },[
	//        'renderClass' => '\MyApp\Render\Account'
	//     ]);
	//
	//	Below are the methods they can override as needed.
	//  The Render class becomes $this inside a template, so all methods here
	//  and on the subclass will be available to the template.


	// hooks which provide an opportunity to get/set templateData before rendering
	public function beforeRenderControllerTemplate() {	}
	public function beforeRenderMasterTemplate(&$html='') { }

	// if the script is at /admin/users.php, the default template will be
	// $controllerTemplateDirectory/admin/users.php
	public function getDefaultTemplateName($inDirectory) {
		$MVCish = $this->MVCish();
		$inDirectory = rtrim($inDirectory,DIRECTORY_SEPARATOR);

		// if a controllerName was found/set, we should match it
		if (isset($MVCish->controllerName) && 
			file_exists($inDirectory.$MVCish->controllerName)
		) return $inDirectory.$MVCish->controllerName;

		// else parse uri
		$urlPath = parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
		$fullfile = $inDirectory.$urlPath;
		if (is_dir($fullfile)) $fullfile .= '/';
		if (substr($fullfile,-1) == '/')    $fullfile .= 'index.php';
		if (substr($fullfile,-4) != '.php') $fullfile .= '.php';
		if (file_exists($fullfile)) return $fullfile;
		//$MVCish->log()->debug("No default template for $fullfile");
	}

	// if the configured or default templateDirectory is MyApp/MVCish/templates,
	// look for primary controller templates by default in MyApp/MVCish/templates/controllers,
	// while fragments or master tempates may be in MyApp/MVCish/templates/otherdirs.
	// if /controlelrs directory does not exist, then return the configured directory.
	// includes trailing slash

	public function getControllerTemplateDirectory() {
		if ($rootTD = $this->getRootTemplateDirectory()) {
			$controllerTD = $rootTD.'controllers';
			return is_dir($controllerTD) ? $controllerTD.DIRECTORY_SEPARATOR : $rootTD;
		}
	}

	public function getEmailTemplateDirectory() {
		if ($rootTD = $this->getRootTemplateDirectory()) {
			$emailTD = $rootTD.'email';
			return is_dir($emailTD) ? $emailTD.DIRECTORY_SEPARATOR : $rootTD;
		}
	}

	// default does not use a master template, just renders the controller template.
	// but subclasses may wish to define a master template into which the controller
	// html will be inserted.

	// returns the filename of the master template, if any. it can either be
	// absolute from document root (if it starts with a '/') or it should be relative
	// from the root template directory.
	public function getMasterTemplateFile() { }

	// as with Controller, look for default $templateRoot/master,
	// or return $templateRoot. includes trailing slash
	public function getMasterTemplateDirectory() {
		if ($rootTD = $this->getRootTemplateDirectory()) {
			$masterTD = $rootTD.'masters';
			return is_dir($masterTD) ? $masterTD.DIRECTORY_SEPARATOR : $rootTD;
		}
	}

}?>
