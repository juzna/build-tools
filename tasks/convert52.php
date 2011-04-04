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

	$renamed = array(
		'Nette\DateTime' => 'DateTime53',
	);

	// simple replacements
	$s = str_replace('__DIR__', 'dirname(__FILE__)', $s);
	$s = str_replace('new static', 'new self', $s);
	$s = str_replace('static::', 'self::', $s);
	$s = preg_replace('#=(.+) \?: #', '= ($tmp=$1) ? $tmp : ', $s); // expand ternary short cut
	$s = preg_replace('#/\\*5\.2\*\s*(.*?)\s*\\*/#s', '$1', $s); // uncomment /*5.2* */
	$s = preg_replace('#/\\*\\*/.*?/\\*\\*/\\s*#s', '', $s);  // remove /**/ ... /**/
	$s = preg_replace("#'NETTE_PACKAGE', '.*'#", "'NETTE_PACKAGE', 'PHP 5.2" . ($prefixed ? ' prefixed' : '') . "'", $s); // loader.php
	$s = str_replace('Nette\Framework::VERSION', ($prefixed ? 'N' : '') . 'Framework::VERSION', $s); // Homepage\default.latte
	$s = str_replace('$application->onStartup[] = function() {', '{', $s); // bootstrap.php
	$s = str_replace('$application->onStartup[] = function() use ($application) {', '{', $s); // bootstrap.php
	$prefixed && $s = str_replace('Nette\Database\Drivers\Pdo', 'NPdo', $s); // Nette\Database\Connection.php


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
				return $renamed[$full];

			} elseif ($prefixed && preg_match('#^(?!I[A-Z])[A-Z]#', $short)) {
				return "N$short";

			} else {
				return $short;
			}
		} else {
			return $short;
		}
	};

	while (($token = $parser->fetch()) !== FALSE) {
 		if ($parser->is(T_NAMESPACE)) {
			$namespace = (string) $parser->fetchAll(T_STRING, T_NS_SEPARATOR);
			if ($parser->fetch(';', '{') === '{') {
				$s .= '{';
			}

		} elseif ($parser->is(T_USE)) {
			if ($parser->isNext('(')) { // closure?
				$s .= $token;
				continue;
			}
			do {
				$class = $parser->fetchAll(T_STRING, T_NS_SEPARATOR);
				$as = $parser->fetch(T_AS)
					? $parser->fetchAll(T_STRING, T_NS_SEPARATOR)
					: substr($class, strrpos("\\$class", '\\'));
				$uses[strtolower($as)] = $class;
			} while ($parser->fetch(','));
			$parser->fetch(';');

		} elseif ($parser->is(T_INSTANCEOF, T_EXTENDS, T_IMPLEMENTS, T_NEW, T_CLASS, T_INTERFACE)) {
			do {
				$s .= $token
					. $parser->fetchAll(T_WHITESPACE)
					. $replaceClass($parser->fetchAll(T_STRING, T_NS_SEPARATOR));
			} while ($token = $parser->fetch(','));

		} elseif ($parser->is(T_STRING, T_NS_SEPARATOR)) { // Class:: or typehint
			$identifier = $token . $parser->fetchAll(T_STRING, T_NS_SEPARATOR);
 			$s .= $parser->isNext(T_DOUBLE_COLON, T_VARIABLE) ? $replaceClass($identifier) : $identifier;

		} elseif ($parser->is(T_DOC_COMMENT, T_COMMENT)) {
			// @var Class or \Class or Nm\Class or Class:: (preserves CLASS, @package)
 			$s .= preg_replace_callback('#((?:@var(?:\s+array of)?|returns?|param|throws|@link|property[\w-]*|@package)\s+)?(?<=\W)(\\\\?[A-Z][\w\\\\]+)(::)?()#', function($m) use ($replaceClass) {
				return $m[1] . (substr($m[1], 0, 8) !== '@package' && preg_match('#[a-z]#', $m[2]) && ($m[1] || $m[3] || strpos($m[2], '\\') !== FALSE) ? $replaceClass($m[2]) : $m[2]) . $m[3];
 			}, $token);

 		} elseif ($parser->is(T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE)) { // strings like 'Nette\Object'
 			$sl = $parser->is(T_CONSTANT_ENCAPSED_STRING) ? '1' : '1,2'; // num of slashes
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
 		if ($parser->is(T_FUNCTION) && $parser->isNext('(')) { // lamda functions
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
			if (strpos($orig, 'String::replace') || strpos($orig, '$context->addService')) {
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
class PhpParser
{
	/** @var array */
	public $ignored = array(T_COMMENT, T_DOC_COMMENT, T_WHITESPACE);

	/** @var int */
	private $pos = 0;

	/** @var array */
	private $tokens;

	private $current;


	function __construct($code)
	{
		$this->tokens = token_get_all($code);
	}


	function fetch()
	{
		return $this->scan(func_get_args(), TRUE);
	}


	function fetchAll($args)
	{
		return $this->scan(func_get_args(), FALSE);
	}


	function fetchUntil($args)
	{
		return $this->scan(func_get_args(), FALSE, TRUE, TRUE);
	}


	function isNext()
	{
		return (bool) $this->scan(func_get_args(), TRUE, FALSE);
	}


	function is()
	{
		return in_array($this->current, func_get_args(), TRUE);
	}


	private function scan($allowed, $first, $advance = TRUE, $neg = FALSE)
	{
		$res = FALSE;
		$pos = $this->pos;
		while (isset($this->tokens[$pos])) {
			$token = $this->tokens[$pos++];
			$r = is_array($token) ? $token[0] : $token;
			if (!$allowed || in_array($r, $allowed, TRUE) ^ $neg) {
				if ($advance) {
					$this->pos = $pos;
					$this->current = $r;
				}
				$res .= is_array($token) ? $token[1] : $token;
				if ($first) {
					break;
				}

			} elseif (!in_array($r, $this->ignored, TRUE)) {
				break;
			}
		}
		return $res;
	}

}