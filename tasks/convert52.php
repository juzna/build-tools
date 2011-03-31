<?php


/**
 * Converts file for PHP 5.2 package
 *
 * @param  string  file to convert
 * @param  bool    prefixed?
 * @param  array   list of classes
 * @return void
 */
$project->convert52 = function($file, $prefixed, array $classes = array()) {

	foreach ($classes as $i => $type) {
		if (($a = strrpos($type, '\\')) && !preg_match('#\\\\I[A-Z][^\\\\]+$#', $type)) {
			$classes[$i] = substr($type, $a + 1); // remove namespace
		} else {
			unset($classes[$i]); // remove interfaces and classes without namespace
		}
	}

	$s = $orig = file_get_contents($file);

	if (strpos($s, '@phpversion 5.3')) { // delete 5.3 files
		unlink($file);
		return;
	}

	// add @package to phpDoc
	if (!strpos($s, '@package')) {
		list(, $namespace) = Nette\String::match($s, '#^namespace\s+(Nette[a-zA-Z0-9_\\\\]*);#m');
		if ($namespace) $s = preg_replace('#^ \*\/#m', " * @package $namespace\n\$0", $s, 1);
	}


	// simple replacements
	$s = str_replace('__DIR__', 'dirname(__FILE__)', $s);
	$s = str_replace('new static', 'new self', $s);
	$s = str_replace('static::', 'self::', $s);
	$s = str_replace('new \DateTime(', 'new DateTime53(', $s);
	$s = preg_replace('#=(.+) \?: #', '= ($tmp=$1) ? $tmp : ', $s); // expand ternary short cut
	$s = preg_replace('#^namespace\s[^;]*?{\r?\n#sm', '{', $s); // remove namespace { }
	$s = preg_replace('#^(namespace|use)\s.*?;\r?\n#sm', '', $s); // remove namespace & use
	$s = preg_replace('#/\\*5\.2\*\s*(.*?)\s*\\*/#s', '$1', $s); // uncomment /*5.2* */
	$s = preg_replace('#/\\*\\*/.*?/\\*\\*/\\s*#s', '', $s);  // remove /**/ ... /**/
	$s = preg_replace('#(\r\n){5,}#is', "\r\n\r\n\r\n\r\n", $s); // consolidate free space
	$s = preg_replace("#'NETTE_PACKAGE', '.*'#", "'NETTE_PACKAGE', 'PHP 5.2" . ($prefixed ? ' prefixed' : '') . "'", $s); // loader.php
	$s = str_replace('Nette\Framework::VERSION', ($prefixed ? 'N' : '') . 'Framework::VERSION', $s); // Homepage\default.latte
	$s = str_replace('$application->onStartup[] = function() {', '{', $s); // bootstrap.php
	$s = str_replace('$application->onStartup[] = function() use ($application) {', '{', $s); // bootstrap.php
	$prefixed && $s = str_replace('Nette\Database\Drivers\Pdo', 'NPdo', $s); // Nette\Database\Connection.php


	// remove namespaces and add prefix
	$o = $name = '';
	foreach (token_get_all($s) as $token) {
		list($id, $token) = is_array($token) ? $token : array(NULL, $token);

 		if ($id === T_STRING || $id === T_NS_SEPARATOR) { // capture identifier name
 			$name .= $token;
 			continue;

		} elseif ($name !== '') {
			$name = preg_replace('#^\\\\?(\w+\\\\)*(\w)#', '$2', $name); // remove namespace from identifiers like Nette\Object or \stdClass or Class
			if ($prefixed && in_array($name, $classes)) {
				$name = "N$name";
			}
			$o .= $name;
 			$name = '';
		}

 		if ($id === T_DOC_COMMENT || $id === T_COMMENT) {
			$token = preg_replace('#(@package\s*Nette)\\\\#', '$1~@~', $token); // protect @package Nette\Xyz
			$token = preg_replace('#(?<=\W)\\\\?([a-z]{3,}\\\\)*(?=[a-z]{3,})#i', '', $token); // identifiers like \stdClass or Nette\Xyz or CLass
			$prefixed && $token = preg_replace('#(?<![\w\\\\~:@-])(' . implode('|', $classes) . ')(?![\w\\\\~-])#', 'N$0', $token); // Nette classes without namespace
			$token = str_replace('~@~', '\\', $token); // restore @package
			$token = str_replace('Nette NFramework', 'Nette Framework', $token); // restore Nette Framework

 		} elseif ($id === T_CONSTANT_ENCAPSED_STRING || $id === T_ENCAPSED_AND_WHITESPACE) { // strings like 'Nette\Object'
 			$sl = $id === T_CONSTANT_ENCAPSED_STRING ? '1' : '1,2'; // num of slashes
			$prefixed && $token = preg_replace('#Nette\\\\{'.$sl.'}(\w+\\\\{'.$sl.'})*((?:' . implode('|', $classes) . ')[ ,.:\'()])#', 'N$2', $token); // Nette\Class
			$token = preg_replace('#Nette\\\\{'.$sl.'}(\w+\\\\{'.$sl.'})*(\w+[ ,.:\'()])#', '$2', $token); // Nette\Interface
		}

		$o .= $token;
	}
	$s = $o . $name;


	// closure support
	if ($file->getFilename() === 'Framework.php') {
		$s .= '
class NClosureFix
{
	static $vars = array();

	static function uses($args)
	{
		self::$vars[] = $args;
		return count(self::$vars)-1;
	}
}';
	}

	// replace closures with create_function
	$scripts = array(); // hide JavaScript to not confuse us
	$s = preg_replace_callback('#<script.*</script>#sU', function($m) use (& $scripts) {
		$scripts[$key = md5($m[0])] = $m[0];
		return $key;
	}, $s);

	$useCallback = strpos($s, 'String::replace') || strpos($s, '$context->addService');

	$s = preg_replace_callback('#([(,=]\s*)function\s*\((.*)\)(?: use \(([^\n]*)\))?\s*{(.{1,2000})}\s*(?=[,;)])#sU', function($matches) use ($useCallback) {
		list($match, $prefix, $args, $uses, $body) = $matches;

		if (strpos($body, 'function(')) {
			return $match;
		}

		$s = "create_function('$args', '";
		if ($uses) {
			$s .= 'extract(NClosureFix::$vars[\'.NClosureFix::uses(array('.preg_replace('#&?\s*\$([^,\s]+)#', "'\$1'=>\$0", $uses).')).\'], EXTR_REFS);';
		}
		$s .= substr(var_export($body, TRUE), 1, -1);
		$s .= "')";

		if ($useCallback) {
			$s = "callback($s)";
		}
		return $prefix . $s;
	}, $s);

	$s = strtr($s, $scripts);


	if ($s !== $orig) {
		file_put_contents($file, $s);
	}
};
