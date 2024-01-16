<?php
namespace AuntieWarhol\MVCish\Util;

class URI {

	/* Uri helpers */

	public function __construct() {	}

	public function getCurrentScheme() {
		return ((!empty($_SERVER['HTTPS'])) && $_SERVER['HTTPS'] !== 'off') ||
			(isset($_SERVER['SERVER_PORT']) && ($_SERVER['SERVER_PORT'] == 443)) ||
			(isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https')
		? "https" : "http";
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
		return $this->getCurrentScheme()."://".$this->getCurrentHost();
	}

	public function getCurrentUrl() {
		return $this->getCurrentSchemeHost() . $_SERVER["REQUEST_URI"];
	}


	public function uriFor($url,$params=[]) {
		if (empty($url)) $url = isset($_SERVER['REDIRECT_URL'])
			? $_SERVER['REDIRECT_URL'] : $_SERVER['PHP_SELF'];
		if (!isset($params)) $params = [];

		$uri = new \AuntieWarhol\MVCish\URI($url,$params);
		if ($uri->isRelative()) {

			if ($scheme = $this->getCurrentScheme()) {
				$uri->scheme($scheme);
			}
			if ($host = $this->getCurrentHost()) {
				$uri->host($host);
			}

			if (substr($url,0,1) != '/') {
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
					$uri->path($path);
				}
			}
		}
		return $uri;
	}

	// adds the v=time query param to js/css links to avoid browser caching issues
	public function assetUriFor($url,$params = [],$opts = []) {
		$uri = new \AuntieWarhol\MVCish\URI($url,$params);
		if ($fullpath = $uri->systemPath()) {
			if (file_exists($fullpath)) {
				$key = isset($opts['stampkey']) ? $opts['stampkey'] : 'v';
				$uri->addToQuery([$key => filemtime($fullpath)]);
			}
		}
		return $uri;
	}
}
?>
