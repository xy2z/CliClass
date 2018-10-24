<?php

namespace xy2z\SlimConsole;

use ReflectionClass;
use ReflectionMethod;
use ReflectionException;

/**
 * SlimConsole
 */
class SlimConsole {

	protected static $argv;
	protected $app;
	protected $method;
	protected $method_params;

	public function __construct($argv, object $app) {
		self::$argv = $argv;
		$this->app = $app;

		if (!isset(self::$argv[1])) {
			$this->list_methods();
			exit;
		}

		$this->method = $this->get_method_info(self::$argv[1]);
		$this->method_params = $this->get_method_parameters($this->method);

		$parameters = array_slice(self::$argv, 2);

		// Check if method is public.
		if (!$this->method->isPublic()) {
			exit('Command is not available (not public)');
		}

		// Check user arguments.
		if ((count($this->method_params) > 0) && !isset(self::$argv[2])) {
			echo 'Usage: ' . $this->method->name . $this->show_method_usage($this->method_params);
			exit;
		}

		// Validate arguments.
		$this->validate_args();

		// Run.
		call_user_func_array([$this->app, $this->method->name], $parameters);
	}

	protected function list_methods() {
		echo 'Available commands:' . PHP_EOL;

		foreach (get_class_methods($this->app) as $method_name) {
			$method = $this->get_method_info($method_name);
			$params = $this->get_method_parameters($method);

			echo ' - ' . $method_name . $this->show_method_usage($params);

			echo PHP_EOL . '     ';
			$this->show_method_description($method);
			echo PHP_EOL . PHP_EOL;
		}
		exit;
	}

	protected function get_method_info($name) {
		$rc = new ReflectionClass($this->app);
		try {
			return $rc->getMethod($name);
		}
		catch (ReflectionException $e) {
			echo 'Unknown method: ' . $name . PHP_EOL . PHP_EOL;
			$this->list_methods();
			exit;
		}
	}

	protected function get_method_parameters($method) {
		return $method->getParameters();
	}

	protected function show_method_usage($method_params) {
		$retval = '';
		foreach ($method_params as $param) {
			$retval .= " [" . $param->getType() . ":" . $param->name . "]";
		}

		return $retval;
	}

	protected function show_method_description(ReflectionMethod $method) {
		$doc = $method->getDocComment();
		if (empty($doc)) {
			return;
		}

		$lines = explode("\n", $doc);
		echo substr(trim($lines[1]), 2);
	}

	protected function validate_args() {
		$i = 2;

		foreach ($this->method_params as $param) {
			if (!$param->isDefaultValueAvailable() && !isset(self::$argv[$i])) {
				exit('Error: Missing argument ' . $i . ' for "' . $param->name . '" (no default value)');
			}

			// Validate user argument is correct typehint
			if ($param->hasType()) {
				if (($param->getType() == 'int') && (!ctype_digit(self::$argv[$i]))) {
					echo 'Error: Argument ' . $i . ' must be an integer.' . PHP_EOL;
					exit;
				}
				if (($param->getType() == 'float') && (!is_numeric(self::$argv[$i]))) {
					echo 'Error: Argument ' . $i . ' must be a float.' . PHP_EOL;
					exit;
				}
			}
			$i++;
		}
	}

}
