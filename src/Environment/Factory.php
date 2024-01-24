<?php
namespace awPHP\MVCish\Environment;

class Factory {

	public final static function getEnvironment($MVCish,$env): mixed {

		if (empty($env)) return new \awPHP\MVCish\MVCish\Environment();

		$className = $env;
		$classObj  = null;

		// pre-constructed object
		if (is_object($className)) {
			$classObj = $className;
		}

		// $\Full\Class\Name
		else if (substr($className,0,1) == '\\') {
			if (class_exists($className)) {
				try {
					$classObj = new $className($MVCish);
				}
				catch(\Throwable $e) {
					throw new \awPHP\MVCish\Exception\ServerError(
						'Failed to instantiate New '.$className.': '
							. $e->getMessage());
				}
			}
		}

		if (isset($classObj)) {
			if (is_a($obj,'awPHP\MVCish\Environment')) {
				return $obj;
			}
			throw new \awPHP\MVCish\Exception\ServerError(
				'Class '.$className.' does not extend awPHP\MVCish\Environment');
		}

		// \awPHP\MVCish\Environment\$ShortName
		else {

			$className = 'awPHP\MVCish\Environment\\'.$env;
			if (class_exists($className)) {
				return new $className($MVCish); 
			}
			else {
				$tryCamel = ucwords(strtolower($env));
				if ($tryCamel != $env) {
					try {
						$classObj = self::getEnvironment($MVCish,$tryCamel);
						return $classObj;
					}
					catch(\Exception $e) {
						// one last try, allow Prod for Production
						if ($tryCamel == 'Prod') {
							try {
								$classObj = self::getEnvironment($MVCish,'Production');
								return $clasObj;
							}
							catch(\Exception $e) {}
						}
					} //otherwise throw error below from original value
				}
			}
		}
		throw new \awPHP\MVCish\Exception\ServerError(
			'Class '.$className.' not found in Environment::Factory');
	}
}
?>
