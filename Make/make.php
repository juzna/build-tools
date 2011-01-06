<?php

/**
 * Nette Make.
 *
 * Copyright (c) 2010 David Grudl (http://davidgrudl.com)
 *
 * This source file is subject to the "Nette license", and/or
 * GPL license. For more information please see http://nette.org
 */

namespace Make;


require __DIR__ . '/Project.php';
require __DIR__ . '/DefaultTasks.php';


// usage: make.php [build-file[:target]] [args, ...]
$args = $_SERVER['argv'];
array_shift($args);
@list($buildFile, $target) = explode(':', array_shift($args));


// initialize project
set_time_limit(0);
date_default_timezone_set('Europe/Prague');

$project = new Project;
$project->verbose = FALSE;
DefaultTasks::initialize($project);


// load build file
$buildFile = $buildFile ?: 'build.php';
if (!is_file($buildFile)) {
	die("Missing build file $buildFile");
}
$project->log("Build file: $buildFile");
require $buildFile;


// run
$target = $target ?: 'main';
$project->run($target, $args);
