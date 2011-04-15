<?php

/**
 * Generates Nette\Loaders\NetteLoader::$list
 *
 * @param  string
 * @return void
 */
$project->netteLoader = function($folder) use ($project) {
	$project->log("Generates Nette\\Loaders\\NetteLoader in $folder");

	// scan for classes
	$folder = realpath($folder);
	$robot = new Nette\Loaders\RobotLoader;
	$robot->setCacheStorage(new Nette\Caching\Storages\DevNullStorage);
	$robot->addDirectory($folder);
	$robot->rebuild();

	// update NetteLoader.php
	$list = array();
	foreach ($robot->getIndexedClasses() as $class => $file) {
		$list[strtolower($class)] = strtr(substr($file, strlen($folder)), '\\', '/');
	}
	ksort($list);
	$s = var_export($list, TRUE);
	$s = str_replace("  '", "\t\t'", $s);
	$s = str_replace(")", "\t)", $s);
	$s = str_replace("array (", "array(", $s);

	$scriptFile = $folder . '/Loaders/NetteLoader.php';
	$script = file_get_contents($scriptFile);
	$script = preg_replace('#= array.*\)#sU', "= $s", $script, -1, $count);
	if ($count !== 1) {
		throw new Exception('NetteLoader injection failed.');
	}
	file_put_contents($scriptFile, $script);
};
