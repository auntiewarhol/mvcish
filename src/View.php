<?php
namespace AuntieWarhol\MVCish;

class View {

	public $MVCish;
	public function __construct(\AuntieWarhol\MVCish\MVCish $MVCish) {
		$this->MVCish = $MVCish;
	}
	function __destruct() {
		unset($this->MVCish);
	}

	public function MVCish() {
		return $this->MVCish;
	}

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
			if (isset($renderClass) ||
				(isset($this->MVCish->options['renderClass']) && 
				($renderClass = $this->MVCish->options['renderClass'])) ||
						((!empty($this->MVCish->Config('renderClass'))) && 
						($renderClass = $this->MVCish->Config('renderClass')))
			) {
				if (class_exists($renderClass)) {
					//$this->MVCish->log()->debug('RenderClass='.$renderClass);
					$this->Render = new $renderClass($this->MVCish);
				}
				else {
					$this->MVCish->log('MVCish')->error("Can't find RenderClass: $renderClass");
					throw new \AuntieWarhol\MVCish\Exception\ServerError('Render Class Not Found');					
				}
			}
			else {
				$this->Render = new View\Render($this->MVCish);
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

			} elseif (isset($this->MVCish->options['view']) &&
				isset($this->_views[strtolower($this->MVCish->options['view'])])
			) {
				$this->_view = strtolower($this->MVCish->options['view']);

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
		if (is_array($this->MVCish->Response) && !empty($this->MVCish->Response['redirect'])) {
			return true;
		}
		return false;
	}

	public function renderError() {
		if (is_array($this->MVCish->Response)) {
			if (isset($this->MVCish->Response['code'])) {
				if (ini_get('output_buffering')) {
					ob_clean();// in case we were mid-render
				}
				http_response_code($this->MVCish->Response['code']);

				if (in_array($this->MVCish->Response['code'],[500,401,403,404])) {
					if (!empty($this->MVCish->Response['redirect'])) {
						// flash messages if redirecting
						$this->MVCish()->processMessages();
					}

					if ($this->view() == 'json') {
						$this->initialize_json();
						// send a 'clean' response, in case it was trying to json_encode
						// the whole response that threw an exception
						echo json_encode([
							'error' => $this->MVCish->Response['error'],
							'code'  => $this->MVCish->Response['code']
						]);
					}
					elseif (!empty($this->MVCish->Response['redirect'])) {
						$this->MVCish()->redirect($this->MVCish->Response['redirect'],
							$this->MVCish->Response['code']);
					}
					elseif (($this->view() != 'text') && 
						($errTemplate = $this->getErrorTemplate($this->MVCish->Response['code']))
					) {
						$this->initialize_html();
						if ($html = $this->Render()->renderFile($errTemplate)) {
							echo $html;
						}
					}
					else {
						$this->initialize_text();
						echo $this->MVCish->Response['error']."\n";
					}
					return false;
				}
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
		if (is_array($this->MVCish->Response) && 
			!empty($this->MVCish->Response['Headers'])
		) {
			foreach($this->MVCish->Response['Headers'] as $header) {
				header($header);
			}
			unset($this->MVCish->Response['Headers']);
		}

		// now render according to the current view
		$meth = '_renderView_'.$this->view();
		$this->$meth();
		return true;
	}

	//************* Views **************
	//

	//** text
	public function initialize_text() {
		if (!$this->MVCish->isCLI()) {
			header('Content-Type: text/plain; charset=utf-8');
		}
	}
	private function _renderView_text() {
		if (is_array($this->MVCish->Response)) {
			if (isset($this->MVCish->Response['Body'])) {
				echo $this->MVCish->Response['Body'];
				return true;
			}
		}
	}

	//** json
	public function initialize_json() {
		header('Content-Type: application/json');
	}
	private function _renderView_json() {
		// flash messages only if we're telling the client to redirect
		if (is_array($this->MVCish->Response) && !empty($this->MVCish->Response['redirect'])) {
			$this->MVCish()->processMessages();
		}
		$this->initialize_json();
		echo json_encode($this->MVCish->Response);
		return true;
	}

	//** xml
	private function _renderView_xml() {
		header('Content-Type: text/xml');
		if (is_array($this->MVCish->Response)) {

			// Can send a pre-produced Body

			if (isset($this->MVCish->Response['Body'])) {
				echo $this->MVCish->Response['Body'];
				return true;
			}
			// #TODO or Data, which we will encode
			// (want to use https://github.com/spatie/array-to-xml,
			// but can't until we're on PHP7)
		}
	}

	//** stream
	private function _renderView_stream() {
		// controller must set Response['streamHandle']
		// can set Reponse['contentType'] or let default to stream
		// can set Response['filename'] to add Content-Disposition header

		$ct = isset($this->MVCish->Response['contentType']) ?
			$this->MVCish->Response['contentType'] : 'application/octet-stream';
		header("Content-Type: $ct");

		// binary unless the mime type is text/*
		if (substr($ct,0,5) !== 'text/') {
			header('Content-Transfer-Encoding: binary');
		}

		if (isset($this->MVCish->Response['filename'])) {
			$filename = str_replace('"','_',$this->MVCish->Response['filename']);
			header('Content-Disposition: attachment; filename="'.$filename.'"');
		}

		if (isset($this->MVCish->Response['streamHandle'])) {
			ob_end_flush();
			ob_implicit_flush();
			$stream = $this->MVCish->Response['streamHandle'];
			while (!feof($stream)) {
				echo fread($stream,1024);
			}
			fclose($stream);
		}
		return true;
	}


	//** csv/excel
	private function _renderView_csv($contentType=null) {
		// controller should set Response['filename'] and Response['rows'];
		// 
		// may also specify Response['rowCallback'] to specify a handler
		// to process/manipulate each row, returning an array

		if (empty($contentType)) $contentType = 'text/csv';
		header("Content-Type: $contentType");

		$filename = isset($this->MVCish->Response['filename']) ?
			$this->MVCish->Response['filename'] : 'download.csv';
		$filename = str_replace('"','_',$this->MVCish->Response['filename']);
		header('Content-Disposition: attachment; filename="'.$filename.'"');

		if (!empty($this->MVCish->Response['rows'])) {
			$callback = (isset($this->MVCish->Response['rowCallback']) &&
				is_callable($this->MVCish->Response['rowCallback'])) ?
					$this->MVCish->Response['rowCallback'] : null;

			foreach ($this->MVCish->Response['rows'] as $row) {
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
		$this->MVCish->processMessages();

		$response = $this->MVCish->Response;
		if (is_object($response) && is_callable([$response,'toArray'])) {
			$response = $response->toArray();
		}
		$this->addHeaders();

		$options  = $this->MVCish->options;
		if (is_array($response)) {
			if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!empty($response['success'])) &&
				empty($response['no_post_redirect'])
			) {
				$uri = isset($options['post_redirect_uri']) ? $options['post_redirect_uri'] :
					(isset($response['redirect']) ?	$response['redirect'] : $_SERVER['REQUEST_URI']);

				if (isset($response['redirect_params'])) {
					$uri = $this->MVCish->uri()->addToQuery($uri,$response['redirect_params']);
				}
				return $this->MVCish->redirect($uri,303);
			}
			elseif(array_key_exists('redirect',$response) && isset($response['redirect'])) {
				return $this->MVCish->redirect($response['redirect']);
			}
			elseif (isset($response['Body'])) {
				// body was provided, just print it, whatever it is.
				// presumes you've also sent whatever Headers you need.
				echo $response['Body'];
				return true;
			}
		}

		// still here, render template
		$this->initialize_html();
		$this->Render()->renderTemplate();
		return true;
	}

	// adds headers from HEADERS in Config, 
	// then calls to add Content-Security-Policy header
	public function addHeaders() {
		$headers = isset($this->MVCish->options['HEADERS']) ?
			$this->MVCish->options['HEADERS'] :
			$this->MVCish->Config('HEADERS');
		if (!empty($headers)) {
			if (is_callable($headers)) {
				$headers = $headers();
			}
			if (!is_array($headers)) $headers = [$headers];
			foreach ($headers as $header => $content) {
				$headerName = ($header === 0) ? '' : $header.': ';
				$headerText = $headerName.$content;
				//$this->MVCish->log('MVCish')->debug("adding header: ".$headerText);
				header($headerText);
			}
		}
		$this->_addCSP();
	}

	// add Content-Security-Policy header from CONTENT_SECURITY_POLICY in Config
	private function _addCSP() {
		$csp = isset($this->MVCish->options['CONTENT_SECURITY_POLICY']) ?
			$this->MVCish->options['CONTENT_SECURITY_POLICY'] :
			$this->MVCish->Config('CONTENT_SECURITY_POLICY');
		if (empty($csp)) return;
		//$this->MVCish->log('MVCish')->debug('CSP data: ',$csp);

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
		//$this->MVCish->log('MVCish')->debug('cspText: '.$cspText);
		header('Content-Security-Policy: '.$cspText);
	}
}

?>
