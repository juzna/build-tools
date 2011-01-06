<?php

/**
 * Converts file for PHP 5.3 package
 *
 * @param  string  file to convert
 * @return void
 */
$project->convert53 = function($file) {
	$s = $orig = file_get_contents($file);

	if (strpos($s, '@phpversion < 5.3')) { // delete 5.2 files
		unlink($file);
		return;
	}

	$s = preg_replace('#/\\*5\.2\*\s*(.*?)\s*\\*/\s*#s', '', $s); // remove /*5.2* */
	$s = preg_replace('#/\\*\\*/(.*?)/\\*\\*/#s', '$1', $s);  // remove comments /**/ ... /**/

	if ($s !== $orig) {
		file_put_contents($file, $s);
	}
};
