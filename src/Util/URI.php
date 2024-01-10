<?php
namespace AuntieWarhol\MVCish\Util;

class URI {

	/* Uri helpers. Doles much of the work out to Enrise\Uri */

	public function __construct() {	}

	public function getCurrentScheme() {
		return ((!empty($_SERVER['HTTPS'])) && $_SERVER['HTTPS'] !== 'off') ||
			(isset($_SERVER['SERVER_PORT']) && ($_SERVER['SERVER_PORT'] == 443)) ||
			(isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')
		? "https://" : "http://";
	}
	//deprecated alias
	public function getProtocol() {
		return $this->getCurrentScheme();
	}

	public function getCurrentHost() {
		// SERVER_NAME vs HTTP_HOST: 
		// https://stackoverflow.com/questions/2297403/what-is-the-difference-between-http-host-and-server-name-in-php
		// either may be a tradeoff, no perfect answer. ymmv and/or this may change.
		// use SERVER_NAME now because it's more secure, but multiple domains pointing to the
		// same code may force us to change our mind later.
		return isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME']
			: (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '');
	}

	public function getCurrentSchemeHost() {
		return $this->getProtocol().$this->getCurrentHost();
	}

	public function getCurrentUrl() {
		return $this->getCurrentSchemeHost() . $_SERVER["REQUEST_URI"];
	}

	public function parse($uri) {

		$parsed = parse_url($uri);
		if (empty($parsed['host'])) { // uri isRelative
			if (substr($uri,0,1) == '/') {
				$uri = $this->getCurrentSchemeHost().$uri;
			}
			else {
				$parts = parse_url($this->getCurrentUrl());
				if (isset($parts['path'])) {
					//if path is directory (ends with /)
					if (substr($parts['path'],-1) == '/') {
						$path = $parts['path'];
					}
					//get path up through directory
					else {
						$pathparts = explode('/',$parts['path']);
						array_pop($pathparts);
						$path = implode('/',$pathparts);
					}
				}
				$uri = $parts['scheme'].'://'.$parts['host'].(isset($path) ? $path : '' ).$uri;
			}
			$parsed = new \League\Uri\Uri($uri);
		}
		return $parsed;
	}

	public function uriFor($uri=null,$params=[]) {
		if (empty($uri)) $uri = isset($_SERVER['REDIRECT_URL'])
			? $_SERVER['REDIRECT_URL'] : $_SERVER['PHP_SELF'];
		if (!isset($params)) $params = [];

		$parsed = $this->parse($uri);
		if ($q = $parsed->getQuery()) {
			$newq = implode('&',[$q,http_build_query($params)]);
		}
		else {
			$newq = http_build_query($params);
		}
		$parsed->setQuery($newq);
		return $parsed->getUri();
	}
	// deprecated alias
	public function addToQuery($uri=null,$params=[]) {
		return $this->uriFor($uri,$params);
	}

	// adds the v=time query param to js/css links to avoid browser caching issues
	public function assetUriFor($uri,$params = [],$opts = []) {
		$parsed = $this->parse($uri);
		$path   = $parsed->getPath();

		$key = isset($opts['stampkey']) ? $opts['stampkey'] : 'v';
		$fullpath = $_SERVER['DOCUMENT_ROOT'].'/'.ltrim($path,'/');
		if (file_exists($fullpath)) $params[$key] = filemtime($fullpath);

		return $this->addToQuery($uri,$params);
	}
}
?>
