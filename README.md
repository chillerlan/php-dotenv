# php-dotenv

Loads contents from a `.env` file into the environment (similar to [vlucas/phpdotenv](https://github.com/vlucas/phpdotenv)). PHP 7.2+

[![version][packagist-badge]][packagist]
[![license][license-badge]][license]
[![Travis][travis-badge]][travis]
[![Coverage][coverage-badge]][coverage]
[![Scrunitizer][scrutinizer-badge]][scrutinizer]
[![Packagist downloads][downloads-badge]][downloads]
[![PayPal donate][donate-badge]][donate]

[packagist-badge]: https://img.shields.io/packagist/v/chillerlan/php-dotenv.svg?style=flat-square
[packagist]: https://packagist.org/packages/chillerlan/php-dotenv
[license-badge]: https://img.shields.io/github/license/chillerlan/php-dotenv.svg?style=flat-square
[license]: https://github.com/chillerlan/php-dotenv/blob/master/LICENSE
[travis-badge]: https://img.shields.io/travis/chillerlan/php-dotenv.svg?style=flat-square
[travis]: https://travis-ci.org/chillerlan/php-dotenv
[coverage-badge]: https://img.shields.io/codecov/c/github/chillerlan/php-dotenv.svg?style=flat-square
[coverage]: https://codecov.io/github/chillerlan/php-dotenv
[scrutinizer-badge]: https://img.shields.io/scrutinizer/g/chillerlan/php-dotenv.svg?style=flat-square
[scrutinizer]: https://scrutinizer-ci.com/g/chillerlan/php-dotenv
[downloads-badge]: https://img.shields.io/packagist/dt/chillerlan/php-dotenv.svg?style=flat-square
[downloads]: https://packagist.org/packages/chillerlan/php-dotenv/stats
[donate-badge]: https://img.shields.io/badge/donate-paypal-ff33aa.svg?style=flat-square
[donate]: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=WLYUNAT9ZTJZ4

# Documentation

## Installation
**requires [composer](https://getcomposer.org)**

*composer.json* (note: replace `dev-master` with a version boundary)
```json
{
	"require": {
		"php": "^7.2",
		"chillerlan/php-dotenv": "^1.0"
	}
}
```

### Manual installation
Download the desired version of the package from [master](https://github.com/chillerlan/php-dotenv/archive/master.zip) or 
[release](https://github.com/chillerlan/php-dotenv/releases) and extract the contents to your project folder.  After that:
- run `composer install` to install the required dependencies and generate `/vendor/autoload.php`.
- if you use a custom autoloader, point the namespace `chillerlan\DotEnv` to the folder `src` of the package 

Profit!

## Usage

```
# example .env
FOO=bar
BAR=foo
WHAT=${BAR}-${FOO}
```

```php
class MyClass{
	use Env;
	
	protected $foo;
	
	public function __construct(){
		// load and overwrite existing vars, require var "WHAT"
		$this->__loadEnv(__DIR__.'/../config', '.env', ['WHAT']);
		
		// will not overwrite
		$this->__addEnv(__DIR__.'/../config', '.env', false, ['WHAT']); 
		
		$this->foo = $_ENV['WHAT']; // -> foo-bar
		// or
		$this->foo = $this->__getEnv('WHAT');
	}
}
```

```php
$env = new DotEnv(__DIR__.'/../config', '.env');
$env->load(['foo']); // foo is required

$foo = $env->get('FOO'); // -> bar

$foo = $env->set('foo', 'whatever');
$foo = $env->get('FOO'); // -> whatever
```
