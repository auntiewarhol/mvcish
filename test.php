<?php

// dumb junk test script. will do this right some day. maybe.

require_once 'vendor/autoload.php';

/*
$a = new \awPHP\MVCish\Primitive\pArray([1,2,3]);
echo "a=".print_r($a,true)."\n";
echo "a=".$a."\n";
echo "a[1]=".$a[1]."\n";
echo "is_array(a)? ".is_array($a)."\n";
echo "is_countable(a)? ".is_countable($a)."\n";
$a[2] = $a[2]+1;
echo "munge= ".print_r($a->value(),true)."\n";
$a->value(array_merge($a(),[5,6]));
echo "merge= ".print_r($a->value(),true)."\n";
array_push($t = &$a(),7,8);
echo "push = ".print_r($a->value(),true)."\n";
//echo "keys = ".print_r(array_keys($a()),true)."\n";
exit;
*/

$MVCish = new \awPHP\MVCish\MVCish([
	'Environment' => isset($_SERVER['MVCISH']) ? $_SERVER['MVCISH'] : 'Local',
	'appConfig' => ['foo' => 'bar']
]);


//trigger dumb warning
preg_match('There will be a warning about missing delimiter here!', 'test');

if ($env = $MVCish->Environment()) {
	echo "ENV = ".$env->name()."\n";
	echo "ENV Class = ".get_class($env)."\n";

	echo "config= " . print_r($MVCish->Config(),true)."\n";
}
$MVCish->log('TEST')->debug("debug message yo");

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
		echo "uriFor= ".$u."\n".print_r($u->toArray(),true)."\n";
	}
	else if ($argv[1] == 'A') {
		$u = $MVCish->uri()->assetUriFor($val);
		echo "assetUriFor= ".$u."\n".print_r($u->toArray(),true)."\n";
	}
}

if ($view = $MVCish->View()) {
	$view->setIllegalProp = true;
	$view->setIllegalPropSneaky[] = true;
}

\awPHP\MVCish\Exception\ServerWarning::throwWarning($MVCish,"test the warning system");
trigger_error("and a regular dumb error",E_USER_ERROR);
echo "still here\n";

echo "bye!\n";
