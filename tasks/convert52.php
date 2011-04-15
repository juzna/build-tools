<?php


/**
 * Converts file for PHP 5.2 package
 *
 * @param  SplFileInfo  file to convert
 * @param  bool    prefixed?
 * @return void
 */
$project->convert52 = function(SplFileInfo $file, $prefixed) {

	$s = $orig = file_get_contents($file);

	if (strpos($s, '@phpversion 5.3')) { // delete 5.3 files
		unlink($file);
		return;
	}

	$renamed = include __DIR__ . '/convert52-renamedClasses.php';

	// simple replacements
	$s = str_replace('__DIR__', 'dirname(__FILE__)', $s);
	$s = str_replace('new static', 'new self', $s);
	$s = str_replace('static::', 'self::', $s);
	$s = preg_replace('#=(.+) \?: #', '= ($tmp=$1) ? $tmp : ', $s); // expand ternary short cut
	$s = preg_replace('#/\\*5\.2\*\s*(.*?)\s*\\*/#s', '$1', $s); // uncomment /*5.2* */
	$s = preg_replace('#/\\*\\*/.*?/\\*\\*/\\s*#s', '', $s);  // remove /**/ ... /**/
	$s = preg_replace("#'NETTE_PACKAGE', '.*'#", "'NETTE_PACKAGE', 'PHP 5.2" . ($prefixed ? ' prefixed' : '') . "'", $s); // loader.php
	$s = str_replace('{=Nette\Framework::VERSION', '{=' . ($prefixed ? 'N' : '') . 'Framework::VERSION', $s); // Homepage\default.latte
	$s = str_replace('$application->onStartup[] = function() {', '{', $s); // bootstrap.php
	$s = str_replace('$application->onStartup[] = function() use ($application) {', '{', $s); // bootstrap.php
	$prefixed && $s = str_replace('Nette\Database\Drivers\\\\', 'N', $s); // Nette\Database\Connection.php


	// remove namespaces and add prefix
	$parser = new PhpParser($s);
	$namespace = $s = '';
	$uses = array('' => '');

	$replaceClass = function ($class) use ($prefixed, &$namespace, &$uses, $renamed) {
		if ($class === 'parent' || $class === 'self') {
			return $class;
		}

		$segment = strtolower(substr($class, 0, strpos("$class\\", '\\')));
		$full = isset($uses[$segment])
			? $uses[$segment] . substr($class, strlen($segment))
			: $namespace . '\\' . $class;
		$full = ltrim($full, '\\');
		$short = substr($full, strrpos("\\$full", '\\'));

		if (substr($full, 0, 6) === 'Nette\\') {
			if (isset($renamed[$full])) {
				$short = $renamed[$full];
			}
			if ($prefixed && preg_match('#^(?!I[A-Z])[A-Z]#', $short)) {
				return "N$short";

			} else {
				return ltrim($short, '\\');
			}
		} else {
			return $short;
		}
	};

	while (($token = $parser->fetch()) !== FALSE) {
 		if ($parser->isCurrent(T_NAMESPACE)) {
			$namespace = (string) $parser->fetchAll(T_STRING, T_NS_SEPARATOR);
			if ($parser->fetch(';', '{') === '{') {
				$s .= '{';
			}

		} elseif ($parser->isCurrent(T_USE)) {
			if ($parser->isNext('(')) { // closure?
				$s .= $token;
				continue;
			}
			do {
				$class = $parser->fetchAll(T_STRING, T_NS_SEPARATOR);
				$as = $parser->fetch(T_AS)
					? $parser->fetch(T_STRING)
					: substr($class, strrpos("\\$class", '\\'));
				$uses[strtolower($as)] = $class;
			} while ($parser->fetch(','));
			$parser->fetch(';');

		} elseif ($parser->isCurrent(T_INSTANCEOF, T_EXTENDS, T_IMPLEMENTS, T_NEW, T_CLASS, T_INTERFACE)) {
			do {
				$s .= $token
					. $parser->fetchAll(T_WHITESPACE)
					. $replaceClass($parser->fetchAll(T_STRING, T_NS_SEPARATOR));
			} while ($token = $parser->fetch(','));

		} elseif ($parser->isCurrent(T_STRING, T_NS_SEPARATOR)) { // Class:: or typehint
			$identifier = $token . $parser->fetchAll(T_STRING, T_NS_SEPARATOR);
 			$s .= $parser->isNext(T_DOUBLE_COLON, T_VARIABLE) ? $replaceClass($identifier) : $identifier;

		} elseif ($parser->isCurrent(T_DOC_COMMENT, T_COMMENT)) {
			// @var Class or \Class or Nm\Class or Class:: (preserves CLASS, @package)
			$s .= preg_replace_callback('#((?:@var(?:\s+array of)?|returns?|param|throws|@link|property[\w-]*|@package)\s+)?(?<=\W)(\\\\?[A-Z][\w\\\\|]+)(::)?()#', function($m) use ($replaceClass) {
				if (substr($m[1], 0, 8) === '@package' || (!$m[1] && !$m[3] && strpos($m[2], '\\') === FALSE)) {
					return $m[0];
				}
				$parts = array();
				foreach (explode('|', $m[2]) as $part) {
					$parts[] = preg_match('#[a-z]#', $part) ? $replaceClass($part) : $part;
				}
				return $m[1] . implode('|', $parts) . $m[3];
			}, $token);

 		} elseif ($parser->isCurrent(T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE)) { // strings like 'Nette\Object'
 			$sl = $parser->isCurrent(T_CONSTANT_ENCAPSED_STRING) ? '1' : '1,2'; // num of slashes
 			$s .= preg_replace_callback('#Nette\\\\{'.$sl.'}(\w+\\\\{'.$sl.'})*\w+(?=[ ,.:\'()])#', function($m) use ($replaceClass) {
				return $replaceClass(str_replace('\\\\', '\\', $m[0]));
 			}, $token);

		} else {
			$s .= $token;
		}
	}


	// replace closures with create_function
	$parser = new PhpParser($s);
	$s = '';
	while (($token = $parser->fetch()) !== FALSE) {
 		if ($parser->isCurrent(T_FUNCTION) && $parser->isNext('(')) { // lamda functions
 			$parser->fetch('(');
			$token = "create_function('" . $parser->fetchUntil(')') . "', '";
			$parser->fetch(')');
 			if ($use = $parser->fetch(T_USE)) {
 				$parser->fetch('(');
				$token .= 'extract(NClosureFix::$vars[\'.NClosureFix::uses(array('
					. preg_replace('#&?\s*\$([^,\s]+)#', "'\$1'=>\$0", $parser->fetchUntil(')'))
					. ')).\'], EXTR_REFS);';
				$parser->fetch(')');
 			}
			$body = '';
 			do {
 				$body .= $parser->fetchUntil('}') . '}';
			} while ($parser->fetch() && !$parser->isNext(',', ';', ')'));

			if (strpos($body, 'function(')) {
				throw new Exception('Nested closure');
			}

			$token .= substr(var_export(substr(trim($body), 1, -1), TRUE), 1, -1) . "')";
			if (strpos($orig, 'StringUtils::replace') || strpos($orig, '$context->addService')) {
				$token = "callback($token)";
			}
		}
		$s .= $token;
	}

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


	// add @package to phpDoc
	if (!strpos($s, '@package') && $namespace) {
		$s = preg_replace('#^ \*\/#m', " * @package $namespace\n\$0", $s, 1);
	}


	// consolidate free space
	$s = preg_replace('#(\r\n){5,}#is', "\r\n\r\n\r\n\r\n", $s);


	if ($s !== $orig) {
		file_put_contents($file, $s);
	}
};



/**
 * Simple tokenizer for PHP.
 *
 * @author     David Grudl
 */
class PhpParser extends Nette\Utils\Tokenizer
{

	function __construct($code)
	{
		$this->ignored = array(T_COMMENT, T_DOC_COMMENT, T_WHITESPACE);
		foreach (token_get_all($code) as $token) {
			$this->tokens[] = is_array($token) ? self::createToken($token[1], $token[0]) : $token;
		}
	}

}