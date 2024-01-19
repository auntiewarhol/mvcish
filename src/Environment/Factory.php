<?php
namespace AuntieWarhol\MVCish\Environment;

class Factory {

	public final static function getEnvironment($env): \AuntieWarhol\MVCish\MVCish\Environment {

		if (empty($env)) return new \AuntieWarhol\MVCish\MVCish\Environment();

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
					$classObj = new $className();
				}
				catch(\Throwable $e) {
					throw new \AuntieWarhol\MVCish\Exception\ServerError(
						'Failed to instantiate New '.$className.': '
							. $e->getMessage());
				}
			}
		}

		if (isset($classObj)) {
			if (is_a($obj,static::class)) {
				return $obj;
			}
			throw new \AuntieWarhol\MVCish\Exception\ServerError(
				'Class '.$className.' does not extend '.(static::class));
		}

		// \AuntieWarhol\MVCish\Environment\$ShortName
		else {

			$className = (static::class).'\\'.$env;
			if (class_exists($className)) {
				return new $className(); 
			}
			else {
				$tryCamel = ucwords(strtolower($env));
				if ($tryCamel != $env) {
					try {
						$classObj = self::getEnvironment($tryCamel);
						return $classObj;
					}
					catch(\Exception $e) {
						// one last try, allow Prod for Production
						if ($tryCamel == 'Prod') {
							try {
								$classObj = self::getEnvironment('Production');
								return $clasObj;
							}
							catch(\Exception $e) {}
						}
					} //otherwise throw error below from original value
				}
			}
		}
		throw new \AuntieWarhol\MVCish\Exception\ServerError(
			'Class '.$className.' not found in Environment::Factory');
	}
}
?>
