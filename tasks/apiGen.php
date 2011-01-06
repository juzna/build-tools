<?php


/**
 * Generates API documentation.
 *
 * @param  string  source directory to parse
 * @param  string  folder to save documentation
 * @return void
 *
 * @depend $project->apiGenExecutable
 * @depend $project->php
 */
$project->apiGen = function($source, $dest) use ($project) {
	$project->log("Generating API documentation for $source");
	$project->php(escapeshellarg($project->apiGenExecutable) . ' -s ' . escapeshellarg($source) . ' -d ' . escapeshellarg($dest));
};
