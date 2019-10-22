<?php

namespace xy2z\SlimConsole;

use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionException;
use Wujunze\Colors;

define('slimconsole_default_pad_length', 10);

/**
 * SlimConsole
 */
class SlimConsole {

	protected static $argv;
	protected $app;
	protected $method;
	protected $method_params;
	public static $colors;

	public function __construct($argv, object $app) {
		self::$argv = $argv;
		$this->app = $app;
		self::$colors = new Colors();
		$method_name = self::$argv[1] ?? null;

		if (!isset($method_name)) {
			$this->list_methods();
			exit;
		}

		$this->method = $this->get_method_info($method_name);
		$this->method_params = $this->get_method_parameters($this->method);

		// Check if method is public.
		if (!$this->method->isPublic()) {
			exit('Command is not available (not public)');
		}
		// Skip magic methods
		if (mb_substr($method_name, 0, 2) === '__') {
			exit('Cannot call magic methods.');
		}

		// Validate arguments.
		$this->validate_args();

		// Run.
		$parameters = array_slice(self::$argv, 2);
		call_user_func_array([$this->app, $this->method->name], $parameters);
	}

	public function print_error($msg) {
		echo self::$colors->getColoredString($msg, 'light_red') . PHP_EOL;
	}

	public function print_method($method_name) {
		echo self::$colors->getColoredString($method_name, 'light_green');
	}

	protected function list_methods() {
		echo 'Usage:' . PHP_EOL;
		$this->print_method(' command [arguments]');
		echo PHP_EOL . PHP_EOL;

		echo 'Available commands:' . PHP_EOL;

		$method_pad_length = slimconsole_default_pad_length;
		$usage_pad_length = slimconsole_default_pad_length;
		$methods = [];

		foreach (get_class_methods($this->app) as $method_name) {
			// Skip magic methods
			if (mb_substr($method_name, 0, 2) === '__') {
				continue;
			}

			$method = $this->get_method_info($method_name);
			$params = $this->get_method_parameters($method);
			$usage = $this->show_method_usage($params);
			$methods[$method_name] = [
				'method' => $method,
				'params' => $params,
				'usage' => $usage,
			];

			if (mb_strlen($method_name) >= $method_pad_length) {
				$method_pad_length = mb_strlen($method_name) + 2;
			}

			if (mb_strlen($usage) >= $usage_pad_length) {
				$usage_pad_length = mb_strlen($usage) + 2;
			}
		}

		foreach ($methods as $method_name => $m) {
			echo ' ';
			$this->print_method(str_pad($method_name, $method_pad_length));
			echo $m['usage'];

			$this->show_method_description($m['method']);
			echo PHP_EOL;
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
		$retval = [];

		if (empty($method_params)) {
			return '';
		}

		foreach ($method_params as $param) {
			$param_usage = self::render_method_param($param);
			$retval[] = $param_usage;
		}

		return implode(' ', $retval) . ' ';
	}

	protected static function render_method_param(Object $param) {
		$optional = false;

		$output = '';

		if ($param->getType()) {
			$output .= self::$colors->getColoredString($param->getType(), 'blue') . ' ';
		}

		$output .= self::$colors->getColoredString('$' . $param->name, 'light_blue');

		if ($param->isDefaultValueAvailable()) {
			$optional = true;
			$default_value = $param->getDefaultValue();
			if (is_null($default_value)) {
				$default_value = 'NULL';
			}
			$output .= self::$colors->getColoredString(" = {$default_value}", 'dark_gray');
		}

		if ($optional) {
			$output = "[$output]";
		}

		return '<' . $output . '>';
	}

	protected function show_method_description(ReflectionMethod $method) {
		$doc = $method->getDocComment();
		if (empty($doc)) {
			return;
		}

		$lines = explode("\n", $doc);
		echo self::$colors->getColoredString(substr(trim($lines[1]), 2), 'green');
	}

	protected function validate_args() {
		$i = 2;

		foreach ($this->method_params as $param) {
			if (!$param->isDefaultValueAvailable() && !isset(self::$argv[$i])) {
				echo 'Usage: ';
				$this->print_method($this->method->name);
				echo ' ' . $this->show_method_usage($this->method_params) . PHP_EOL;

				$this->print_error('Error: Missing argument ' . $i . ' for $' . $param->name . ' (no default value)');
				exit;
			}

			// Validate user argument is correct typehint
			if (isset(self::$argv[$i]) && $param->hasType()) {
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
