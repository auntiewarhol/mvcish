<?php
namespace awPHP\MVCish;

class View extends Base {

	private $_views = [
		'html' => true, 'json' => true, 'stream' => true,
		'csv' => true, 'csvexcel' => true, 'xml' => true,
		'text' => true
	];

	private $Render;
	public function Render($renderClass=null) {
		if (isset($renderClass) || !isset($this->Render)) {

			// use RenderClass subclass if one is defined
			$renderHelper = null;
			if ($renderClass ??= ($this->MVCish()->Options('renderClass') ?? $this->MVCish()->Config('renderClass'))) {
				if (class_exists($renderClass)) {
					//$this->MVCish()->log()->debug('RenderClass='.$renderClass);
					$this->Render = new $renderClass($this->MVCish());
				}
				else {
					throw new Exception\ServerError('Render Class '.$renderClass.' Not Found');					
				}
			}
			else {
				$this->Render = new View\Render($this->MVCish());
			}
		}
		return $this->Render;
	}

	private $_view = null;
	public function view($setview=null) {
		if ($setview || empty($this->_view)) {

			if (isset($setview) &&
				isset($this->_views[strtolower($setview)])
			) {
				$this->_view = strtolower($setview);

			} elseif (
				($optViewName = $this->MVCish()->Options('view')) &&
				isset($this->_views[strtolower($optViewName)])
			) {
				$this->_view = strtolower($optViewName);

			} elseif (isset($_REQUEST['view']) &&
				isset($this->_views[strtolower($_REQUEST['view'])])
			) {
				$this->_view = strtolower($_REQUEST['view']);

			} elseif ($this->MVCish()->isCLI()) {
				$this->_view = 'text';

			} else {
				$this->_view = 'html'; //default
			}
		}
		return $this->_view;
	}

	public function shouldFlashMessages() {

		// always in html
		if ($this->view() == 'html') return true;

		// only if redirecting in json
		if ($this->MVCish()->Response()->hasRedirect()) {
			return true;
		}
		return false;
	}

	public function renderError() {
		$MVCish = $this->MVCish();
		if ($respCode = $MVCish->Response()->code()) {
			$error = $MVCish->Response()->error();

			if (ini_get('output_buffering')) {
				ob_clean();// in case we were mid-render
			}
			http_response_code($respCode);

			if (in_array($respCode,[500,401,403,404])) {

				if ($redirect = $MVCish->Response()->redirect()) {
					// flash messages if redirecting
					$MVCish->processMessages();
				}

				if ($this->view() == 'json') {
					$this->initialize_json();
					// send a 'clean' response, in case it was trying to json_encode
					// the whole response that threw an exception
					echo json_encode(['error' => $error, 'code' => $respCode]);
				}
				elseif (isset($redirect)) {
					$MVCish->redirect($redirect,$respCode);
				}
				elseif (($this->view() != 'text') && 
					($errTemplate = $this->getErrorTemplate($respCode))
				) {
					$this->initialize_html();
					if ($html = $this->Render()->renderFile($errTemplate)) {
						echo $html;
					}
				}
				else {
					$this->initialize_text();
					echo "Exited with Error: ".$error."\n";
				}
				return false;
			}
		}
		//otherwise, render normally
		return $this->renderView();
	}

	public function getErrorTemplate($code) {
		if ($td = $this->Render()->getRootTemplateDirectory()) {
			$errTemplate = $td.$code.'.php';
			if (file_exists($errTemplate)) return $errTemplate;
		}
	}

	public function renderView() {

		// add any headers indicated by the response
		if ($headers = $this->MVCish()->Response()->headers()) {
			foreach($headers as $header) {
				header($header);
			}
			$this->MVCish()->Response()->headers(null,[]);
		}

		// now render according to the current view
		$this->MVCish()->log('MVCish')->debug("Rendering View: ".$this->view());
		$meth = '_renderView_'.$this->view();
		$this->$meth();
		return true;
	}

	//************* Views **************
	//

	//** text
	public function initialize_text() {
		if (!$this->MVCish()->isCLI()) {
			header('Content-Type: text/plain; charset=utf-8');
		}
	}
	private function _renderView_text() {
		if ($this->MVCish()->Response()->hasBody()) {
			echo $this->MVCish()->Response()->body();
			return true;
		}
	}

	//** json
	public function initialize_json() {
		header('Content-Type: application/json');
	}
	private function _renderView_json() {
		// flash messages only if we're telling the client to redirect
		if ($this->MVCish()->Response()->hasRedirect()) {
			$this->MVCish()->processMessages();
		}
		$this->initialize_json();
		echo json_encode($this->MVCish()->Response());
		return true;
	}

	//** xml
	private function _renderView_xml() {
		header('Content-Type: text/xml');
		if ($body = $this->MVCish()->Response()->body()) {

			// Can send a pre-produced Body
			echo $body;
			return true;
			// #TODO or Data, which we will encode
			// want to use https://github.com/spatie/array-to-xml?
		}
	}

	//** stream
	private function _renderView_stream() {
		// controller must set Response['streamHandle']
		// can set Reponse['contentType'] or let default to stream
		// can set Response['filename'] to add Content-Disposition header

		$ct = $this->MVCish()->Response('contentType') ?? 'application/octet-stream';
		header("Content-Type: $ct");

		// binary unless the mime type is text/*
		if (substr($ct,0,5) !== 'text/') {
			header('Content-Transfer-Encoding: binary');
		}

		if ($filename = $this->MVCish()->Response()->filename()) {
			$filename = str_replace('"','_',$filename);
			header('Content-Disposition: attachment; filename="'.$filename.'"');
		}

		if ($stream = $this->MVCish()->Response()->streamHandle()) {
			ob_end_flush();
			ob_implicit_flush();
			while (!feof($stream)) {
				echo fread($stream,1024);
			}
			fclose($stream);
		}
		return true;
	}


	//** csv/excel
	private $defaultDownloadCSVFilename = 'download.csv';

	private function _renderView_csv($contentType=null) {
		// controller should set Response->filename() and Response->rows();
		// 
		// may also specify Response['rowCallback'] to specify a handler
		// to process/manipulate each row, returning an array

		if (empty($contentType)) $contentType = 'text/csv';
		header("Content-Type: $contentType");

		$filename =	$this->MVCish()->Response()->filename() ?? $this->defaultDownloadCSVFilename;
		$filename = str_replace('"','_',$filename);
		header('Content-Disposition: attachment; filename="'.$filename.'"');

		if ($rows = $this->MVCish()->Response->rows()) {

			$callback =	(($cb = $this->MVCish()->Response()->rowCallback()) &&
				is_callable($cb)) ? $cb : null;

			foreach ($rows as $row) {
				if ($callback) $row = $callback($row);
				echo implode(',',array_map(function($n) {
					return "\"$n\"";
				},$row))."\n";
			}
		}
		return true;
	}
	// same thing but sets the mime type so it opens in excel
	private function _renderView_csvexcel() {
		return $this->_renderView_csv('application/vnd.ms-excel');
	}


	//** html
	public function initialize_html() {
		header('Content-Type: text/html; charset=utf-8');
	}
	private function _renderView_html() {
		// flash messages
		$this->MVCish()->processMessages();

		$response = $this->MVCish()->Response();
		$this->addHeaders();

		$options = $this->MVCish()->Options();

		if (($_SERVER['REQUEST_METHOD'] === 'POST') &&
			$response->success() && !$response->noPostRedirect()
		) {
			$uri = isset($options['post_redirect_uri']) ? $options['post_redirect_uri'] :
				($response->redirect() ?? $_SERVER['REQUEST_URI']);

			if ($redirectParams = $response->redirectParams()) {
				$uri = $this->MVCish()->uriFor($uri,$redirectParams);
			}
			return $this->MVCish()->redirect($uri,303);
		}
		elseif ($redirect = $response->redirect()) {
			return $this->MVCish()->redirect($redirect);
		}
		elseif ($response->hasBody()) {
			// body was provided, just print it, whatever it is.
			// presumes you've also sent whatever Headers you need.
			echo $response->body();
			return true;
		}

		// still here, render template
		$this->initialize_html();
		$this->Render()->renderTemplate();
		return true;
	}

	// adds headers from HEADERS in Config, 
	// then calls to add Content-Security-Policy header
	public function addHeaders() {
		if ($headers = $this->MVCish()->OptionConfig('HEADERS')) {
			if (is_callable($headers)) {
				$headers = $headers();
			}
			if (!is_array($headers)) $headers = [$headers];
			foreach ($headers as $header => $content) {
				$headerName = ($header === 0) ? '' : $header.': ';
				$headerText = $headerName.$content;
				//$this->MVCish()->log('MVCish')->debug("adding header: ".$headerText);
				header($headerText);
			}
		}
		$this->_addCSP();
	}

	// add Content-Security-Policy header from CONTENT_SECURITY_POLICY in Config
	private function _addCSP() {
		if (!($csp = $this->MVCish()->OptionConfig('CONTENT_SECURITY_POLICY'))) return;
		//$this->MVCish()->log('MVCish')->debug('CSP data: ',$csp);

		if (is_array($csp)) {
			$cspText = '';
			foreach ($csp as $srcType => $sources) {
				$srcText = ($srcType === 0) ? '' : $srcType.' ';
				if (!is_array($sources)) $sources = [$sources];
				$cspText .= $srcText.implode(' ',$sources).'; ';
			}
		}
		else {
			$cspText = $csp;
		}
		//$this->MVCish()->log('MVCish')->debug('cspText: '.$cspText);
		header('Content-Security-Policy: '.$cspText);
	}
}

?>
