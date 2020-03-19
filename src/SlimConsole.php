<?php

namespace xy2z\CliClass;

use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionException;
use Wujunze\Colors;

define('cliclass_default_pad_length', 10);

/**
 * CliClass
 */
abstract class CliClass {

	protected static $argv;
	protected static $classes;
	protected static $commands;
	protected static $colors;
	protected static $alias_separator = ':';

	protected static $method;
	protected static $command;
	protected static $method_params;
	protected static $command_class;
	protected static $class_index;

	public static function init(array $argv, array $classes) {
		self::$argv = $argv;
		self::$classes = $classes;
		self::$colors = new Colors();

		self::$command = self::$argv[1] ?? null;

		if (!isset(self::$command)) {
			echo 'Usage:' . PHP_EOL;
			self::print_method(' command [arguments]');
			echo PHP_EOL . PHP_EOL;

			self::list_methods();
			exit;
		}

		// Run command.
		self::run_command(self::$command);
	}

	/**
	 * Find which class a command (method) belongs to.
	 */
	protected static function find_command($command) {
		foreach (self::$classes as $index => $class) {
			foreach (get_class_methods($class) as $method_name) {
				if ($method_name == $command) {
					return $index;
				}

				if (is_string($index)) {
					// Class alias
					$alias_command = $index . self::$alias_separator . $method_name;
					if ($alias_command == $command) {
						return $index;
					}
				}
			}
		}
		return false;
	}

	/**
	 * Run the command the user has entered.
	 */
	protected static function run_command() {
		// Find which class the method belongs to.
		self::$class_index = self::find_command(self::$command);
		if (self::$class_index === false) {
			self::print_error('Error: Unknown command (' . self::$command . ')');
			echo PHP_EOL;
			self::list_methods();
			exit;
		}

		// If class is using alias, extract the method name from the command.
		$method_name = self::$command;
		if (is_string(self::$class_index)) {
			$method_name = substr($method_name, mb_strlen(self::$class_index) + mb_strlen(self::$alias_separator));
		}

		self::$command_class = self::$classes[self::$class_index];

		self::$method = self::get_method_info(self::$command_class, $method_name);
		self::$method_params = self::get_method_parameters(self::$method);

		// Check if method is public.
		if (!self::$method->isPublic()) {
			self::print_error('Error: Command is not available (not public)');
		}
		// Skip magic methods
		if (mb_substr($method_name, 0, 2) === '__') {
			self::print_error('Error: Cannot call magic methods.');
		}

		// Validate arguments.
		self::validate_args();

		// Execute
		$parameters = array_slice(self::$argv, 2);
		if (!self::$method->isStatic()) {
			// Method is not static, so we must create an object.
			self::$command_class = new self::$command_class;
		}
		call_user_func_array([self::$command_class, self::$method->name], $parameters);
	}

	public static function print_error($msg) {
		echo self::$colors->getColoredString($msg, 'light_red') . PHP_EOL;
	}

	public static function print_method($method_name, $class_alias = null) {
		if (is_string($class_alias)) {
			$method_name = $class_alias . self::$alias_separator . $method_name;
		}
		echo self::$colors->getColoredString($method_name, 'light_green');
	}

	protected static function list_methods() {
		echo 'Available commands:' . PHP_EOL;

		foreach (self::$classes as $alias => $class) {
			self::list_class_methods($class, $alias);
		}
	}

	protected static function list_class_methods($class, $class_alias) {
		$method_pad_length = cliclass_default_pad_length;
		$usage_pad_length = cliclass_default_pad_length;
		$methods = [];

		foreach (get_class_methods($class) as $method_name) {
			// Skip magic methods
			if (mb_substr($method_name, 0, 2) === '__') {
				continue;
			}

			$method = self::get_method_info($class, $method_name);
			$params = self::get_method_parameters($method);
			$usage = self::show_method_usage($params);
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
			self::print_method(str_pad($method_name, $method_pad_length), $class_alias);
			echo $m['usage'];

			self::show_method_description($m['method']);
			echo PHP_EOL;
		}
	}

	protected static function get_method_info($class, $name) {
		$rc = new ReflectionClass($class);
		try {
			return $rc->getMethod($name);
		}
		catch (ReflectionException $e) {
			self::print_error('Error: Could not get method: ' . $name . '. ' . $e->getMessage());
			exit;
		}
	}

	protected static function get_method_parameters($method) {
		return $method->getParameters();
	}

	protected static function show_method_usage($method_params) {
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

	protected static function show_method_description(ReflectionMethod $method) {
		$doc = $method->getDocComment();
		if (empty($doc)) {
			return;
		}

		$lines = explode("\n", $doc);
		echo self::$colors->getColoredString(substr(trim($lines[1]), 2), 'green');
	}

	protected static function validate_args() {
		$i = 2;

		foreach (self::$method_params as $param) {
			if (!$param->isDefaultValueAvailable() && !isset(self::$argv[$i])) {
				echo 'Usage: ';
				self::print_method(self::$method->name, self::$class_index);
				echo ' ' . self::show_method_usage(self::$method_params) . PHP_EOL;

				self::print_error('Error: Missing argument ' . $i . ' for $' . $param->name . ' (no default value)');
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
