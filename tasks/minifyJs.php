<?php


/**
 * Minifies Javascript a CSS.
 *
 * @param  string  file to process
 * @return void
 *
 * @depend $project->compilerExecutable (and Java)
 */
$project->minifyJs = function($file) use ($project) {
	$project->log("Shrinking JS & CSS in $file");

	$s = $orig = file_get_contents($file);

	// insert netteQ.js into Nette\Debug\templates\bar.phtml
	$s = preg_replace_callback('#<\?php require .*/NetteQ/netteQ\.js\' \?>#', function() use ($file) {
		return file_get_contents($file->getPath() . '/../../../client-side/NetteQ/netteQ.js');
	}, $s);

	// compress JS & CSS fragments
	$s = preg_replace_callback('#(<(script|style).*>)(.*)(</)#Uis', function($m) use ($project) {
		list(, $start, $type, $s, $end) = $m;

		if (strpos($s, '<?php') !== FALSE) {
			return $m[0];

		} elseif ($type === 'script') { // JS compressor
			$process = proc_open('java -jar ' . escapeshellarg($project->compilerExecutable) . ' --charset utf-8', array(
				0 => array('pipe', 'r'),
				1 => array('pipe', 'w'),
				2 => array('pipe', 'w'),
			), $pipes);

			fwrite($pipes[0], $s);
			fclose($pipes[0]);

			$s = trim(stream_get_contents($pipes[1]));
			fclose($pipes[1]);

			if ($ret = proc_close($process)) {
				throw new Exception("Google Closure Compiler returned $ret");
			}

			if (strpos($s, '<') !== FALSE || strpos($s, '&') !== FALSE) {
				$s = '/*<![CDATA[*/'.$s.'/*]]>*/';
			}

		} else { // CSS compressor
			$s = preg_replace('#/\*.*?\*/#s', '', $s); // remove comments
			$s = preg_replace('#\s+#', ' ', $s); // compress space
			$s = preg_replace('# ([^0-9a-z.\#*-])#i', '$1', $s);
			$s = preg_replace('#([^0-9a-z%)]) #i', '$1', $s);
			$s = str_replace(';}', '}', $s); // remove leading semicolon
			$s = trim($s);
		}

		return $start . $s . $end;
	}, $s);

	if ($s !== $orig) {
		file_put_contents($file, $s);
	}
};
