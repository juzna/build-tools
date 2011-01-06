<?php

/**
 * Helpers finding order to load all files in framework.
 */

$folder = $_SERVER['argv'][1];


$types = array_merge(get_declared_interfaces(), get_declared_classes());

require $folder . '/loader.php';

foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($folder)) as $entry) {
	if (pathinfo($entry, PATHINFO_EXTENSION) === 'php') {
		require_once $entry;
	}
}

$types = array_diff(array_merge(get_declared_interfaces(), get_declared_classes()), $types);

$files = array();
foreach (array_reverse($types) as $type) {
	$rc = new ReflectionClass($type);
	$files[$rc->getFileName()] = TRUE;
}
$files = array_reverse(array_keys($files));

echo json_encode($files);
