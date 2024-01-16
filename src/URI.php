<?php
namespace AuntieWarhol\MVCish;

class URI implements \JsonSerializable {


	private string $_url;
	private array  $_components;

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
	public function __toString(): string  { return $this->url(); }

	public function jsonSerialize():mixed { return $this->toArray(); }

	public function toArray() {
		return array_merge($this->getComponents(),[
			'authority' => $this->authority(),
			'origin'    => $this->origin(),
			'url'       => $this->url(),
			'params'    => $this->queryParams(),
			// testing only
			//'systemPath' => $this->systemPath()
		]);
	}

	private function _buildUrl() {
		if ($components = $this->getComponents()) {

			if ($this->isAbsolute()) {
				$origin = $this->origin();
			}
			$path      = $components['path']     ?? "";
			$query     = $components['query']    ?? "";
			$fragment  = $components['fragment'] ?? "";

			//error_log("origin= ".(empty($origin) ? 'null' : $origin));
			//error_log("path= $path, query= $query, frag= $fragment");

			$this->_url =
				($origin ?? "") .
				(\strlen($path)     > 0 ? $path : "") .
				(\strlen($query)    > 0 ? "?$query" : "") .
				(\strlen($fragment) > 0 ? "#$fragment" : "");
		}
	}

	public function getComponents($setComponents=null) {
		if (empty($this->_components)) {
			$this->_components = parse_url($this->_url);
		}
		if (is_array($setComponents)) {
			foreach($setComponents as $k => $v) {
				$this->_changed = true;
				$this->_components[$k] = $v;
			}
		}
		//error_log("components= ".print_r($this->_components,true));
		return $this->_components;
	}
	public function getComponent($name,$new=null) {
		if (
			($components = $this->getComponents(isset($new) ? [$name => $new] : null))
			&& array_key_exists($name,$components)
		) {
			return $components[$name];
		}
	}


	// Scheme
	public function scheme($new=null)   { return $this->getComponent('scheme',$new);   }

	// Authority
	public function host($new=null)     { return $this->getComponent('host',$new);     }
	public function port($new=null)     { return $this->getComponent('port',$new);     }
	public function user($new=null)     { return $this->getComponent('user',$new);     }
	public function pass($new=null)     { return $this->getComponent('pass',$new);     }
	public function authority() {
		if ($components = $this->getComponents()) {
			if ($this->isAbsolute()) {
				$pass      = $components['pass'] ?? null;
				$user      = $components['user'] ?? null;
				$host      = $components['host'] ?? null;
				$port      = $components['port'] ?? null;
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
		if ($components = $this->getComponents()) {
			if ($this->isAbsolute()) {
				$scheme    = $components['scheme'] ?? "";
				$authority = $this->authority();
				//error_log("scheme= $scheme, auth= $authority");
				return (\strlen($scheme) > 0 ? "$scheme:" : "") .
					(\strlen($authority) > 0 ? "//$authority" : "");
			}
		}
	}


	// Path
	public function path()     { return $this->getComponent('path');     }
	public function systemPath() {
		$path = $this->path();
		if (!empty($_SERVER['DOCUMENT_ROOT'])) {
			$filepath = $_SERVER['DOCUMENT_ROOT'].DIRECTORY_SEPARATOR
				.(isset($path) ? ltrim($path,DIRECTORY_SEPARATOR) : '').DIRECTORY_SEPARATOR;
			if (file_exists($filepath)) return $filepath;
		}
	}

	public function isRelative() { return $this->host() ? false : true;  }
	public function isAbsolute() { return $this->host() ? true  : false; }



	// Query 
	private $_qryParams = null;
	public function query($new=null,$newParams=null) {
		if (isset($new)) {
			$this->_qryParams = null;
			$this->getComponent('query',$new);
		}
		else {
			if (isset($newParams)) {
				$this->_qryParams = $newParams;
			}
			if (isset($this->_qryParams)) {
				$this->getComponent('query',http_build_query($this->_qryParams));
			}
		}
		return $this->getComponent('query');
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
	public function fragment($new=null) { return $this->getComponent('fragment',$new); }
}
