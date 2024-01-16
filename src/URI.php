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
	
				$pass      = $parsed['pass'] ?? null;
				$user      = $parsed['user'] ?? null;
				$userinfo  = $pass !== null ? "$user:$pass" : $user;
				$port      = $parsed['port'] ?? 0;
				$scheme    = $parsed['scheme'] ?? "";
				$query     = $parsed['query'] ?? "";
				$fragment  = $parsed['fragment'] ?? "";
				$authority = (
					($userinfo !== null ? "$userinfo@" : "") .
					($parsed['host'] ?? "") .
					($port ? ":$port" : "")
				);
				$base =
					(\strlen($scheme) > 0 ? "$scheme:" : "") .
					(\strlen($authority) > 0 ? "//$authority" : "") .
					($parsed['host'] ?? "");
			}
			$this->_url = ($base ?? "") .
				($parsed['path'] ?? "") .
				(\strlen($query) > 0 ? "?$query" : "") .
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

	public function scheme($new=null)   { return $this->urlComponent('scheme',$new);   }
	public function host($new=null)     { return $this->urlComponent('host',$new);     }
	public function port($new=null)     { return $this->urlComponent('port',$new);     }
	public function user($new=null)     { return $this->urlComponent('user',$new);     }
	public function pass($new=null)     { return $this->urlComponent('pass',$new);     }
	public function fragment($new=null) { return $this->urlComponent('fragment',$new); }

	// Path
	public function path()     { return $this->urlComponent('path');     }
	public function systemPath() {
		$path = $this->path();
		return $_SERVER['DOCUMENT_ROOT'].(isset($path) ? $path : '').DIRECTORY_SEPARATOR;
	}

	public function isRelative() { return $this->host() ? false : true;  }
	public function isAbsolute() { return $this->host() ? true  : false; }


	// Query handling
	private $_qryParams = null;
	private $_qryString = null;
	public function query($new=null) {
		if (isset($new)) {
			$this->_qryString = $new;
			$this->_qryParams = null;
		}
		else if (empty($this->_qryString)) {
			if (isset($this->_qryParams)) {
				$this->_qryString = http_build_query($this->_qryParams);
			}
			else if ($q = $this->urlComponent('query')) {
				$this->_qryString = $q;
			}
		}
		return $this->_qryString;
	}
	public function queryParams($new=null) {
		if (isset($new)) {
			$this->_qryParams = $new;
			$this->_qryString = null;
		}
		else if (!isset($this->_qryParams)) {
			if ($q = $this->query()) {
				$params = [];
				parse_str($q, $params);
				$this->_qryParams = $params;
			}
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

}
