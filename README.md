# SlimConsole

Create a simple CLI tool from a PHP class.
- All public methods will be available to run from cli.
- PHPdocs will be displayed as the description.
- Method arguments are automatically loaded.


## Requires
- PHP 7.0 or above


## Install
`composer require xy2z/slim-console`


## Usage
```php
require '/path/to/vendor/autoload.php';

use xy2z\SlimConsole\SlimConsole;

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

new SlimConsole($argv, new Router());
```


### Result
```bash
$ php cli.php
Available commands:
 - hello_world
     Says hello world.

 - hello [string:name]
     Says hello to $name.
```

```bash
$ php cli.php hello_world
Hello world.
```

```bash
$ php cli.php hello
Usage: hello [string:name]
```

```bash
$ php cli.php hello Peter
Hello Peter
```
