<?php

require_once 'vendor/autoload.php';

$MVCish = new \AuntieWarhol\MVCish\MVCish();

if ((!empty($argv[1])) && (!empty($argv[2]))) {
	$val = $argv[2];
	if ($argv[1] == 'H') {

		$parsedHost = parse_url($val, PHP_URL_HOST);
		echo "parsed '".$parsedHost."' from '".$val."'\n";
		if (empty($parsedHost) && !empty($val)) $parsedHost = $val;
		echo "using '".$parsedHost."'\n";

		$parser = $MVCish->domainParser();
		if ($result = $parser->resolvePublicSuffixList($parsedHost)) {
			$suffix = $result->suffix();
			if ($suffix->isICANN()) {
				echo $parsedHost." is ICANN\n";
			}
			else {
				echo $parsedHost." is NOT ICANN\n";
			}
			echo "resultDomain= "  .$result->domain()->toString()."\n";
			echo "suffixDomain= "  .$suffix->domain()->toString()."\n";
			echo "2L Domain= "     .$result->secondLevelDomain()->toString()."\n";
			echo "SubDomain= "     .$result->subDomain()->toString()."\n";
			echo "Rg Domain= "     .$result->registrableDomain()->toString()."\n";
			echo "isICANN= "       .$suffix->isICANN()."\n";
			echo "isPrivate= "     .$suffix->isPrivate()."\n";
			echo "isPublicSuffix= ".$suffix->isPublicSuffix()."\n";
			echo "isIANA= "        .$suffix->isIANA()."\n";
			echo "isKnown= "       .$suffix->isKnown()."\n";
			//echo print_r($result,true)."\n";
		}
		else {
			echo "no result\n";
		}
	}
	else if ($argv[1] == 'U') {
		$u = $MVCish->uri()->uriFor($val,['foo'=>'bar']);
		echo "uriFor= ".$u."\n";
		echo "u= ".print_r($u->toArray(),true)."\n";
	}
	else if ($argv[1] == 'A') {
		$u = $MVCish->uri()->assetUriFor($val);
		echo "uriFor= ".$u."\n";
		echo "u= ".print_r($u->toArray(),true)."\n";
	}
}


echo "bye!\n";
