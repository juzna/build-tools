<?php

namespace Make;



/**
 * Make project.
 *
 * @author     David Grudl
 */
class Project
{
	/** @var bool */
	public $verbose = FALSE;

	/** @var array */
	private $callStack = array();



	public function run($default = NULL, array $args = array())
	{
		set_error_handler(function($severity, $message, $file, $line) {
			if (($severity & error_reporting()) === $severity) {
				throw new \ErrorException($message, 0, $severity, $file, $line);
			}
			return FALSE;
		});

		$time = microtime(TRUE);

		try {
			$this->callStack = array();
			$this->__call($default ?: 'main', $args);
			$this->log("\nBUILD FINISHED\n");

		} catch (\Exception $e) {
			$this->log("Execution failed: {$e->getMessage()} at {$e->getFile()}:{$e->getLine()}");
			if ($this->verbose) {
				$this->log($e->getTraceAsString());
			}
			$this->log("\nBUILD FAILED\n");
		}

		restore_error_handler();
		$this->log('Total time: ' . number_format(microtime(TRUE) - $time, 1, '.', ' ') . ' seconds');
	}



	/**
	 * Displays a message to the output.
	 */
	public function log($message)
	{
		if (!$this->verbose && count($this->callStack) > 2) {
			return;
		}
		$task = end($this->callStack);
		echo ($task ? str_pad("[$task] ", 15 * (count($this->callStack) - 1), ' ', STR_PAD_LEFT) : '') . "$message\n";
		flush();
	}



	/**
	 * Allows calling functions stored in properties.
	 */
	public function __call($name, $args)
	{
		if (!isset($this->$name)) {
			throw new \Exception("Call to undefined task '$name'");
		}

		array_push($this->callStack, $name);
		try {
			$res = call_user_func_array($this->$name, $args);
			array_pop($this->callStack);
			return $res;

		} catch (\Exception $e) {
			array_pop($this->callStack);
			throw $e;
		}
	}



	public function __get($name)
	{
		throw new \Exception("Cannot read an undeclared property \$$name");
	}

}
