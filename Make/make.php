<?php

/**
 * Nette Make.
 *
 * Copyright (c) 2011 David Grudl (http://davidgrudl.com)
 *
 * This source file is subject to the "Nette license", and/or
 * GPL license. For more information please see http://nette.org
 */

namespace Make;


require __DIR__ . '/Project.php';
require __DIR__ . '/DefaultTasks.php';


// initialize project
set_time_limit(0);
date_default_timezone_set('Europe/Prague');

$options = getopt('f:t:a:v');

$project = new Project;
$project->verbose = isset($options['v']);
DefaultTasks::initialize($project);


// load build file
$buildFile = isset($options['f']) ? $options['f'] : 'build.php';
if (!is_file($buildFile)) {
	die("Missing build file $buildFile");
}
$project->log("Build file: $buildFile");
require $buildFile;


// run
$target = isset($options['t']) ? $options['t'] : 'main';
$args = isset($options['a']) ? (array) $options['a'] : array();
$project->run($target, $args);
