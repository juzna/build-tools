<?php


/**
 * Executing PHP command.
 *
 * @param  string  command line
 * @param  string  path to php.exe
 * @return string  output
 *
 * @depend $project->phpExecutable optionally
 */
$project->php = function($cmd, $executable = NULL) use ($project) {
	$project->log("Running PHP command $cmd");
	return $project->exec(escapeshellarg($executable ? $executable : $project->phpExecutable) . ' ' . $cmd);
};




/**
 * Checking syntax of PHP source file.
 *
 * @param  string  file to check
 * @param  string  path to php.exe
 * @return void
 *
 * @depend $project->php
 */
$project->phpLint = function($file, $executable = NULL) use ($project) {
	$project->log("Checking syntax for $file");
	$project->php('-l ' . escapeshellarg($file), $executable);
};
