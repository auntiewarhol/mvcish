<?php
namespace AuntieWarhol\MVCish;

class URI implements Stringable {

	/* make stringable objects out of URI strings */

	private string $_url;
	public function __construct($url="",$params=[],$replace=false) {
		$this->_url = $url;
		$replace ? $this->replaceQuery($params) : $this->addToQuery($params);
	}

	public function write():string { return $this->_url; }
	public __toString(): string { return $this->write(); }



	/* 3rd party parser does most of the real work. But we want
		to provide consistent interface in case we have to swap 
		out the 3rd party library. again.
	*/

	public function getQuery() {
		if ($parsed = $this->_parsed()) {
			return $parsed->getQuery();
		}
	}
	public function setQuery($qry) {
		if ($parsed = $this->_parsed()) {
			$parsed->setQuery($qry);
		}
	}

	public function addToQuery($params) {
		if ($q = $this->getQuery()) {
			$newq = empty($params) ? $q : 
				implode('&',[$q,http_build_query($params)]);
			$this->setQuery($newq);
		}
		else {
			return $this->replaceQuery($params);
		}
	}
	public function replaceQuery($params) {
		$newq = empty($params) ? '' : http_build_query($params);
		$this->setQuery($newq);
	}

	public function getPath() {
		if ($parsed = $this->_parsed()) {
			return ltrim($parsed->getPath(),DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
		}
	}
	public function getFullPath() {
		return $_SERVER['DOCUMENT_ROOT'].DIRECTORY_SEPARATOR.$this->getPath();
	}


	private $_parsed;
	private function _parsed($url=null) {
		if (isset($url) ||
			(empty($this->_parsed) && ($url = $this->_url))
		) {
			$this->_parsed = $this->_parseWa72($url);
		}
		return $this->_parsed;
	}

	private function _parseWa72($uri) {

		$parsed = new \Wa72\Url\Url($uri);
		if (!$parsed->getHost()) { // uri isRelative
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
			$parsed = new \Wa72\Url\Url($uri);
		}
		return $parsed;
	}
}
