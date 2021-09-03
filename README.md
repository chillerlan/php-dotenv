# php-dotenv

Loads contents from a `.env` file into the environment (similar to [vlucas/phpdotenv](https://github.com/vlucas/phpdotenv)). PHP 7.4+

[![PHP Version Support][php-badge]][php]
[![version][packagist-badge]][packagist]
[![license][license-badge]][license]
[![Coverage][coverage-badge]][coverage]
[![Scrunitizer][scrutinizer-badge]][scrutinizer]
[![Packagist downloads][downloads-badge]][downloads]
[![CI][gh-action-badge]][gh-action]

[php-badge]: https://img.shields.io/packagist/php-v/chillerlan/php-dotenv?logo=php&color=8892BF
[php]: https://www.php.net/supported-versions.php
[packagist-badge]: https://img.shields.io/packagist/v/chillerlan/php-dotenv.svg?logo=packagist
[packagist]: https://packagist.org/packages/chillerlan/php-dotenv
[license-badge]: https://img.shields.io/github/license/chillerlan/php-dotenv.svg
[license]: https://github.com/chillerlan/php-dotenv/blob/master/LICENSE
[coverage-badge]: https://img.shields.io/codecov/c/github/chillerlan/php-dotenv.svg?logo=codecov
[coverage]: https://codecov.io/github/chillerlan/php-dotenv
[scrutinizer-badge]: https://img.shields.io/scrutinizer/g/chillerlan/php-dotenv.svg?logo=scrutinizer
[scrutinizer]: https://scrutinizer-ci.com/g/chillerlan/php-dotenv
[downloads-badge]: https://img.shields.io/packagist/dt/chillerlan/php-dotenv.svg?logo=packagist
[downloads]: https://packagist.org/packages/chillerlan/php-dotenv/stats
[gh-action-badge]: https://github.com/chillerlan/php-dotenv/workflows/CI/badge.svg
[gh-action]: https://github.com/chillerlan/php-dotenv/actions?query=workflow%3A%22CI%22

# Documentation

## Installation
**requires [composer](https://getcomposer.org)**

*composer.json* (note: replace `dev-main` with a [version constraint](https://getcomposer.org/doc/articles/versions.md#writing-version-constraints))

```json
{
	"require": {
		"php": "^7.4",
		"chillerlan/php-dotenv": "dev-main"
	}
}
```

Installation via terminal: `composer require chillerlan/php-dotenv`

Profit!

## Usage

```
# example .env
FOO=bar
BAR=foo
WHAT=${BAR}-${FOO}
```

```php
$env = new DotEnv(__DIR__.'/../config', '.env');
$env->load(['foo']); // foo is required

// get a variable
$foo = $_ENV['FOO']; // -> bar
$foo = $env->get('FOO'); // -> bar
$foo = $env->FOO; // -> bar

// dynamically set a variable
$env->set('foo', 'whatever');
$env->FOO = 'whatever';

$foo = $env->get('FOO'); // -> whatever
// ...

// variable substitution
$foo = $env->get('WHAT'); // -> foo-bar
```

```php
// avoid the global environment
$env = (new DotEnv(__DIR__.'/../config', '.env', false))->load();

$foo = $_ENV['FOO']; // -> undefined
$foo = $env->get('FOO'); // -> bar
$foo = $env->FOO; // -> bar
```
