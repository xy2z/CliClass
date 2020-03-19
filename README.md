# CliClass

(previously known as SlimConsole)

Create a simple CLI tool from a PHP class.

- All public methods will be available to run from cli.
- PHPdocs will be displayed as the description.
- Method arguments are automatically validated.
- Supports multiple classes.


## Requires
- PHP 7.0 or above


## Install
`composer require xy2z/cliclass`


## Usage
```php
require '/path/to/vendor/autoload.php';

use xy2z\CliClass\CliClass;

class Router {
	/**
	 * Says hello world.
	 */
	public function hello_world() {
		echo 'Hello world.';
	}

	/**
	 * Says hello to $name.
	 */
	public function hello(string $name) {
		echo 'Hello ' . $name;
	}
}

CliClass::init($argv, [
	Router::class,
]);

```


### Result
```bash
$ php cli.php
Usage:
 command [arguments]

Available commands:
 hello_world  Says hello world.
 hello        <string $name> Says hello to $name.
```

```bash
$ php cli.php hello_world
Hello world.
```

```bash
$ php cli.php hello
Usage: hello <string $name>
Error: Missing argument 2 for $name (no default value)
```

```bash
$ php cli.php hello Peter
Hello Peter
```
