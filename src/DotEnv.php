<?php
/**
 * Class DotEnv
 *
 * @created      07.09.2018
 * @author       Smiley <smiley@chillerlan.net>
 * @copyright    2018 Smiley
 * @license      MIT
 *
 * @noinspection PhpComposerExtensionStubsInspection
 */
declare(strict_types=1);

namespace chillerlan\DotEnv;

use function apache_getenv, apache_setenv, array_key_exists, array_map, explode, file, function_exists, getenv,
	implode, in_array, is_array, is_file, is_numeric, is_readable, preg_replace, preg_replace_callback, putenv,
	rtrim, sprintf, str_contains, str_starts_with, strtoupper, trim;
use const DIRECTORY_SEPARATOR, FILE_IGNORE_NEW_LINES, FILE_SKIP_EMPTY_LINES, PHP_EOL;

/**
 * Loads .env config files into the environment
 *
 * $_ENV > getenv()!
 *
 * @link http://php.net/variables-order
 */
class DotEnv{

	/**
	 * a backup environment in case everything goes downhill
	 */
	protected array $_ENV = [];

	/**
	 * Sets the global $_ENV if true. Otherwise all variables are being kept internally
	 * in $this->_ENV to avoid leaking, making them only accessible via DotEnv::get().
	 */
	protected bool $global;

	/**
	 * the path to the .env file
	 */
	protected string $path;

	/**
	 * an optional file name in case it differs from ".env"
	 */
	protected string|null $filename;

	/**
	 * DotEnv constructor.
	 */
	public function __construct(string $path, string|null $filename = null, bool|null $global = null){
		$this->path     = $path;
		$this->filename = $filename;
		$this->global   = $global ?? true;
	}

	public function __get(string $var):string|null{
		return $this->get($var);
	}

	public function __set(string $var, string|null $value):void{
		$this->set($var, $value);
	}

	public function __isset(string $var):bool{
		return $this->isset($var);
	}

	public function __unset(string $var):void{
		$this->unset($var);
	}

	/**
	 * (re-)loads the currently set .env file into the environment
	 */
	public function load(array|null $required = null):DotEnv{
		return $this->loadEnv($this->path, $this->filename, true, $required, $this->global);
	}

	/**
	 * loads a .env file into the environment
	 */
	public function loadEnv(
		string      $path,
		string|null $filename = null,
		bool|null   $overwrite = null,
		array|null  $required = null,
		bool|null   $global = null,
	):DotEnv{
		$this->global = $global ?? true;
		$file         = rtrim($path, '\\/').DIRECTORY_SEPARATOR.($filename ?? '.env');
		$content      = $this->read($file);

		return $this
			->loadData($content, $overwrite ?? false)
			->check($required)
		;
	}

	/**
	 * adds the values from the given .env to the currently set values
	 */
	public function addEnv(
		string      $path,
		string|null $filename = null,
		bool|null   $overwrite = null,
		array|null  $required = null,
	):DotEnv{
		return $this->loadEnv($path, $filename, $overwrite, $required, $this->global);
	}

	/**
	 * gets a variable from the environment
	 */
	public function get(string $var):string|null{
		$var = strtoupper($var);
		$env = null;

		if($this->global === true){

			if(array_key_exists($var, $_ENV)){
				$env = $_ENV[$var];
			}
			elseif(function_exists('getenv')){
				$env = getenv($var);
			}
			// @codeCoverageIgnoreStart
			elseif(function_exists('apache_getenv')){
				$env = apache_getenv($var);
			}
			// @codeCoverageIgnoreEnd

		}

		$value = $env ?? $this->_ENV[$var] ?? null;

		if(empty($value)){
			return null;
		}

		return $value;
	}

	/**
	 * sets a variable in the environment
	 */
	public function set(string $var, string|null $value = null):DotEnv{
		$var   = strtoupper($var);
		$value = $this->parse($value);

		if($this->global === true){
			putenv($var.'='.$value);

			// fill $_ENV explicitly, assuming variables_order="GPCS" (production)
			$_ENV[$var] = $value;

			// @codeCoverageIgnoreStart
			if(function_exists('apache_setenv')){
				apache_setenv($var, $value ?? '');
			}
			// @codeCoverageIgnoreEnd
		}

		// a backup
		$this->_ENV[$var] = $value;

		return $this;
	}

	/**
	 * checks if the given variable is set in the environment
	 */
	public function isset(string $var):bool{

		if($this->global === true){

			// @codeCoverageIgnoreStart
			if(function_exists('apache_getenv') && apache_getenv($var)){
				return true;
			}
			// @codeCoverageIgnoreEnd

			if(getenv($var) || !empty($_ENV[$var])){
				return true;
			}
		}


		return !empty($this->_ENV[$var]);
	}

	/**
	 * unsets/removes a variable from the environment)
	 */
	public function unset(string $var):DotEnv{
		$var = strtoupper($var);

		if($this->global === true){
			unset($_ENV[$var]);
			putenv($var);

			// @codeCoverageIgnoreStart
			if(function_exists('apache_setenv')){
				apache_setenv($var, '');
			}
			// @codeCoverageIgnoreEnd
		}

		unset($this->_ENV[$var]);

		return $this;
	}

	/**
	 * clears the environment variables (in $_ENV)
	 *
	 * use with caution!
	 */
	public function clear():DotEnv{

		if($this->global === true){
			$_ENV = [];
		}

		$this->_ENV = [];

		return $this;
	}

	/**
	 * reads the given .env file
	 *
	 * @throws \chillerlan\DotEnv\DotEnvException
	 */
	protected function read(string $file):array{

		if(!is_file($file) || !is_readable($file)){
			throw new DotEnvException('invalid file: '.$file);
		}

		$lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

		if(!is_array($lines) || empty($lines)){
			throw new DotEnvException('error while reading file: '.$file);
		}

		return array_map('trim', $lines);
	}

	/**
	 * loads a data array
	 *
	 * @see \chillerlan\DotEnv\DotEnv::read()
	 */
	protected function loadData(array $data, bool $overwrite):DotEnv{

		foreach($data as $line){

			// skip empty lines and comments
			if(empty($line) || str_starts_with($line, '#')){
				continue;
			}

			$kv = array_map('trim', explode('=', $line, 2));

			// skip empty and numeric keys, keys with spaces, existing keys that shall not be overwritten
			if(
				empty($kv[0])
				|| is_numeric($kv[0])
				|| str_contains($kv[0], ' ')
				|| (!$overwrite && $this->get($kv[0]) !== false)
			){
				continue;
			}

			$this->set($kv[0], isset($kv[1]) ? trim($kv[1]) : null);
		}

		return $this;
	}

	/**
	 * parses the given value
	 */
	protected function parse(string|null $value = null):string{

		if($value === null){
			return '';
		}

		$q = $value[0] ?? null;

		$value = in_array($q, ["'", '"'], true)
			// handle quoted strings
			? preg_replace("/^$q((?:[^$q\\\\]|\\\\\\\\|\\\\$q)*)$q.*$/mx", '$1', $value)
			// skip inline comments
			: trim(explode('#', $value, 2)[0]);

		// handle multiline values
		$value = implode(PHP_EOL, explode('\\n', $value));

		// handle nested ${VARS}
		if(str_contains($value, '$')){
			$value = preg_replace_callback('/\${(?<var>[_a-z\d]+)}/i', fn($matches) => $this->get($matches['var']), $value);
		}

		return $value;
	}

	/**
	 * checks if a set of keys exists in the environment - case-sensitive!
	 *
	 * @throws \chillerlan\DotEnv\DotEnvException
	 */
	protected function check(array|null $required = null):DotEnv{

		if(empty($required)){
			return $this;
		}

		$checked = [];

		foreach($required as $var){
			if(!$this->isset($var)){
				$checked[] = strtoupper($var);
			}
		}

		if(!empty($checked)){
			throw new DotEnvException(sprintf('required variable(s) not set: "%s"', implode(', ', $checked)));
		}

		return $this;
	}

}
