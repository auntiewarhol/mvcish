<?php
namespace AuntieWarhol\MVCish\Environment;

class Production extends \AuntieWarhol\MVCish\Environment {

	public string $name = "Production";
	public string $loggerLevel = [
		'DEFAULT' => 'Warning', 'CLI' => 'Debug'
	];
	public string $defaultAppConfigFilename = 'appConfig-Production.php';

}
