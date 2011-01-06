<?php

namespace Make;

use RecursiveIteratorIterator,
	RecursiveDirectoryIterator;



/**
 * Set of default tasks.
 *
 * @author     David Grudl
 */
class DefaultTasks
{
	/** @var Project */
	private $project;


	static function initialize(Project $project)
	{
		$tasks = new self;
		$tasks->project = $project;
		foreach (get_class_methods(__CLASS__) as $method) {
			$project->$method = array($tasks, $method);
		}
	}


	/**
	 * Executes a command on the shell.
	 */
	function exec($cmd)
	{
		$this->project->log("Executing command $cmd");
		exec($cmd, $output, $res);
		if ($res !== 0) {
			throw new \Exception("Task exited with code $res");
		}
		return implode(PHP_EOL, $output);
	}



	/**
	 * Creates directory when it does not exist.
	 */
	function createDir($path)
	{
		$this->project->log("Creating directory $path");
		if (!is_dir($path)) {
			mkdir($path, 0777, TRUE);
		}
	}



	/**
	 * Copies a file or directory to a new location. Directories are only copied
	 * if the when the destination does not exist. Files will be overwritten.
	 */
	function copy($source, $dest)
	{
		if (is_dir($source)) {
			$this->project->log("Copying directory $source to $dest");
			mkdir($dest, 0777, TRUE);
			// foreach ($iterator = Nette\Finder::find('*')->from($source)->getIterator() as $item) {
			foreach ($iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST) as $item) {
				if ($item->isDir()) {
					mkdir($dest . '/' . $iterator->getSubPathName());
				} else {
					copy($item, $dest . '/' . $iterator->getSubPathName());
				}
			}

		} elseif (is_file($source)) {
			$this->project->log("Copying file $source to $dest");
			if (!is_dir($dest)) {
				mkdir(dirname($dest), 0777, TRUE);
			}
			copy($source, $dest);

		} else {
			throw new \Exception("File $source not found.");
		}
	}



	/**
	 * Deletes a file or directory.
	 */
	function delete($path)
	{
		if (is_dir($path)) {
			$this->project->log("Deleting directory $path");
			// foreach (Nette\Finder::find('*')->from($path)->childFirst() as $item) {
			foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
				if ($item->isDir()) {
					rmdir($item);
				} else {
					unlink($item);
				}
			}
			rmdir($path);

		} elseif (is_file($path)) {
			$this->project->log("Deleting file $path");
			unlink($path);
		}
	}



	/**
	 * Replaces the occurrence of a given regular expression
	 * with a substitution pattern in a file.
	 */
	function replace($file, array $replacements)
	{
		$s = $orig = file_get_contents($file);
		$s = preg_replace(array_keys($replacements), array_values($replacements), $s);
		if ($s !== $orig) {
			$this->project->log("Replacing texts in $file");
			file_put_contents($file, $s);
		}
	}

}
