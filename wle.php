<?php

require_once 'vendor/autoload.php';

use Frontiernxt\LeScriptUpdater;

if(!defined("PHP_VERSION_ID") || PHP_VERSION_ID < 50300 || !extension_loaded('openssl') || !extension_loaded('curl')) {
    die("You need at least PHP 5.3.0 with OpenSSL and curl extension\n");
}

// Always use UTC
date_default_timezone_set("UTC");


$updater = new LeScriptUpdater(getcwd().'/config.yaml');

echo "validating profiles\n";

try {
	
	$updater->validateProfiles();

} catch (\Exception $e) {
	
	echo "\n".$e->getMessage()."\n";

   //exit(1);
}

echo "validating domains\n";

try {
	
	$updater->validateDomains();

} catch (\Exception $e) {
	
	echo "\n".$e->getMessage()."\n";

   //exit(1);
}



$updater->run();

exit;
