<?php
namespace AuntieWarhol\MVCish;

class URI {


	private string $_url;
	public function __construct($url,$params=null,$replace=false) {
		$this->_changed = false;
		$this->_url = isset($url) ? $url : '';
		if (isset($params)) {
			$replace ? $this->queryParams($params) : $this->addToQuery($params);
		}
	}

	public function url():string {
		if ($this->_changed) $this->_buildUrl();
		return $this->_url;
	}
	public function __toString(): string { return $this->url(); }

	private function _buildUrl() {
		if ($parsed = $this->getComponents()) {

			if ($this->isAbsolute()) {
				$origin = $this->origin();
			}
			$path      = $parsed['path']     ?? "";
			$query     = $parsed['query']    ?? "";
			$fragment  = $parsed['fragment'] ?? "";
			$this->_url =
				($origin ?? "") .
				(\strlen($path)     > 0 ? $path : "") .
				(\strlen($query)    > 0 ? "?$query" : "") .
				(\strlen($fragment) > 0 ? "#$fragment" : "");
		}
	}


	private $_parsed = null;
	public function getComponents($setComponents=null) {
		if (is_array($setComponents)) {
			$this->_parsed = parse_url($this->_url);
			foreach($setComponents as $k => $v) {
				$this->_changed = true;
				$this->_parsed[$k] = $v;
			}
		}
		elseif (empty($this->_parsed)) {
			$this->_parsed = parse_url($this->_url);
		}
		return $this->_parsed;
	}
	public function urlComponent($name,$new=null) {
		if (
			($parsed = $this->getComponents(isset($new) ? [$name => $new] : null))
			&& array_key_exists($name,$parsed)
		) {
			return $parsed[$name];
		}
	}


	// Scheme
	public function scheme($new=null)   { return $this->urlComponent('scheme',$new);   }

	// Authority
	public function host($new=null)     { return $this->urlComponent('host',$new);     }
	public function port($new=null)     { return $this->urlComponent('port',$new);     }
	public function user($new=null)     { return $this->urlComponent('user',$new);     }
	public function pass($new=null)     { return $this->urlComponent('pass',$new);     }
	public function authority() {
		if ($parsed = $this->getComponents()) {
			if ($this->isAbsolute()) {
				$pass      = $parsed['pass'] ?? null;
				$user      = $parsed['user'] ?? null;
				$host      = $parsed['host'] ?? null;
				$port      = $parsed['port'] ?? null;
				$userinfo  = $user == null ? null :
					($pass != null ? "$user:$pass" : $user);
				return (
					($userinfo != null ? "$userinfo@" : "") .
					($host ?? "") .
					($port ? ":$port" : "")
				);
			}
		}
	}

	// Origin
	public function origin() {
		if ($parsed = $this->getComponents()) {
			if ($this->isAbsolute()) {
				$scheme    = $parsed['scheme'] ?? "";
				$authority = $this->authority();
				return (\strlen($scheme) > 0 ? "$scheme:" : "") .
					(\strlen($authority) > 0 ? "//$authority" : "");
			}
		}
	}


	// Path
	public function path()     { return $this->urlComponent('path');     }
	public function systemPath() {
		$path = $this->path();
		return $_SERVER['DOCUMENT_ROOT'].(isset($path) ? $path : '').DIRECTORY_SEPARATOR;
	}

	public function isRelative() { return $this->host() ? false : true;  }
	public function isAbsolute() { return $this->host() ? true  : false; }



	// Query 
	private $_qryParams = null;
	public function query($new=null,$newParams=null) {
		if (isset($new)) {
			$this->_qryParams = null;
			$this->urlComponent('query',$new);
		}
		else {
			if (isset($newParams)) {
				$this->_qryParams = $newParams;
			}
			if (isset($this->_qryParams)) {
				$this->urlComponent('query',http_build_query($this->_qryParams));
			}
		}
		return $this->urlComponent('query');
	}
	public function queryParams($new=null) {
		if (isset($new)) {
			$this->query(null,$new);
		}
		else if ((!isset($this->_qryParams)) && ($q = $this->query())) {
			$params = [];
			parse_str($q, $params);
			$this->_qryParams = $params;
		}
		return $this->_qryParams;
	}

	// raw end-of-string add
	public function addToQuery($params) {
		if ($q = $this->query()) {
			if (!empty($params)) {
				$this->query(implode('&',[$q,http_build_query($params)]));
			}
		}
		else {
			$this->queryParams($params);
		}
	}


	// Fragment
	public function fragment($new=null) { return $this->urlComponent('fragment',$new); }
}
