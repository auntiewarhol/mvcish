<?php
namespace AuntieWarhol\MVCish\Util;
use Pdp\Rules;
use Pdp\Domain;
use Pdp\TopLevelDomains;
use Symfony\Component\HttpClient\HttpClient;

class DomainParser {

	const pslTTLsec = 86400; //24hr


	// wrapper around php-domain-parser to handle the caching of the public list,
	// and to abstract away some of its formalities
	public function __construct() {	

		$cacheDir = dirname(__FILE__).'/.dpcache';
		if (!(is_dir($cacheDir) && is_writable($cacheDir))) {
			if (!mkdir($cacheDir,0777)) {
				$cacheDir = sys_get_temp_dir();
			}
		}
			

		$this->_lists = [];
		$this->_files = [
			'publicSuffix' => $cacheDir.'/public_suffix_list.dat',
			'tld'          => $cacheDir.'/tlds-alpha-by-domain.txt'
		];
		$this->_fromPathClass = [
			'publicSuffix' => 'Pdp\Rules',
			'tld'          => 'Pdp\TopLevelDomains'
		];
		$this->_URI = [
			'publicSuffix' => 'https://publicsuffix.org/list/public_suffix_list.dat',
			'tld'          => 'https://data.iana.org/TLD/tlds-alpha-by-domain.txt'
		];
	}

	public function publicSuffixListFile() {
		return $this->_files['publicSuffix'];
	}
	public function tldListFile() {
		return $this->_files['tld'];
	}


	private function _refreshCacheFile($which,$ignoreTTL = false) {

		$file = $this->_files[$which];
		if (file_exists($file)) {
			$secDiff = (time()-filemtime($file));
			if ((!$ignoreTTL) && ($secDiff < self::pslTTLsec)) {
				return true;
			}
		}
	
		$client = HttpClient::create();
		$response = $client->request('GET', $this->_URI[$which]);
		if ($response->getStatusCode() == 200) {
			if (file_put_contents($file,$response->getContent())) {
				return true;
			}
		}
		return false;
	}

	// attempt to refresh if needed. return file if it exists whether or not refreshed
	private function _getRefreshCacheFile($which) {
		$file = $this->_files[$which];
		if (($this->_refreshCacheFile($which)) || (file_exists($file))) {
			return $file;
		}
	}

	private function _getList($which) {
		if (empty($this->_lists[$which])) {
			$file = $this->_files[$which];
			if ($this->_getRefreshCacheFile($which)) {
				try {
					$this->_lists[$which] = ($this->_fromPathClass[$which])::fromPath($file);
				}
				catch(\Exception $e) {
					error_log("Caught exception processing file: ".$e->getMessage());
					return;
				}
			}
		}
		return $this->_lists[$which];
	}

	public function getPublicSuffixListObject() {
		return $this->_getList('publicSuffix');
	}
	public function getTLDListObject() {
		return $this->_getList('tld');
	}

	public function getDomainObject($domain) {
		return Domain::fromIDNA2008($domain);
	}

	public function resolvePublicSuffixList($domain) {
		if ($o = $this->getPublicSuffixListObject()) {
			return $o->resolve($this->getDomainObject($domain));
		}
	}
	public function resolveTLDList($domain) {
		if ($o = $this->getTLDListObject()) {
			return $o->resolve($this->getDomainObject($domain));
		}
	}


}
?>
