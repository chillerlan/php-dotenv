<?php
/**
 * Class DotEnv
 *
 * @filesource   DotEnv.php
 * @created      07.09.2018
 * @package      chillerlan\DotEnv
 * @author       Smiley <smiley@chillerlan.net>
 * @copyright    2018 Smiley
 * @license      MIT
 *
 * @noinspection PhpComposerExtensionStubsInspection
 */

namespace chillerlan\DotEnv;

use function apache_getenv, apache_setenv, array_key_exists, array_map, explode, file, function_exists, getenv, implode,
	in_array, ini_get, ini_set, is_array, is_file, is_numeric, is_readable, preg_replace, preg_replace_callback, putenv,
	rtrim, strpos, strtoupper, trim;

use const DIRECTORY_SEPARATOR, FILE_IGNORE_NEW_LINES, FILE_SKIP_EMPTY_LINES, PHP_EOL;

/**
 * Loads .env config files into the environment
 *
 * $_ENV > getenv()!
 *
 * @link https://github.com/vlucas/phpdotenv
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
	protected ?string $filename;

	/**
	 * DotEnv constructor.
	 *
	 * @param string      $path
	 * @param string|null $filename
	 * @param bool|null   $global
	 */
	public function __construct(string $path, string $filename = null, bool $global = null){
		$this->path     = $path;
		$this->filename = $filename;
		$this->global   = $global ?? true; // emulate vlucas/dotenv behaviour by default
	}

	/**
	 * @param string $var
	 *
	 * @return mixed|null
	 */
	public function __get(string $var){
		return $this->get($var);
	}

	/**
	 * @param string $var
	 * @param        $value
	 */
	public function __set(string $var, $value):void{
		$this->set($var, $value);
	}

	/**
	 * @param string $var
	 *
	 * @return bool
	 */
	public function __isset(string $var):bool{
		return $this->isset($var);
	}

	/**
	 * @param string $var
	 */
	public function __unset(string $var):void{
		$this->unset($var);
	}

	/**
	 * @param array|null $required
	 *
	 * @return \chillerlan\DotEnv\DotEnv
	 */
	public function load(array $required = null):DotEnv{
		return $this->loadEnv($this->path, $this->filename, true, $required, $this->global);
	}

	/**
	 * @param string      $path
	 * @param string|null $filename
	 * @param bool|null   $overwrite
	 * @param array|null  $required
	 * @param bool|null   $global
	 *
	 * @return \chillerlan\DotEnv\DotEnv
	 */
	public function loadEnv(
		string $path,
		string $filename = null,
		bool $overwrite = null,
		array $required = null,
		bool $global = null
	):DotEnv{
		$this->global = $global ?? true;
		$file         = rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.($filename ?? '.env');
		$content      = $this->read($file);

		return $this
			->loadData($content, $overwrite ?? false)
			->check($required)
		;
	}

	/**
	 * @param string      $path
	 * @param string|null $filename
	 * @param bool|null   $overwrite
	 * @param array|null  $required
	 *
	 * @return \chillerlan\DotEnv\DotEnv
	 */
	public function addEnv(string $path, string $filename = null, bool $overwrite = null, array $required = null):DotEnv{
		return $this->loadEnv($path, $filename, $overwrite, $required, $this->global);
	}

	/**
	 * @param string $var
	 *
	 * @return mixed|null
	 */
	public function get(string $var){
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

		return $env ?? $this->_ENV[$var] ?? null;
	}

	/**
	 * @param string      $var
	 * @param string|null $value
	 *
	 * @return \chillerlan\DotEnv\DotEnv
	 */
	public function set(string $var, string $value = null):DotEnv{
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
	 * @param string $var
	 *
	 * @return bool
	 */
	public function isset(string $var):bool{
		return (
				$this->global && (
					isset($_ENV[$var])
					|| getenv($var)
					|| (function_exists('apache_getenv') && apache_getenv($var))
				)
			)
			|| array_key_exists($var, $this->_ENV);
	}

	/**
	 * @param string $var
	 *
	 * @return \chillerlan\DotEnv\DotEnv
	 */
	public function unset(string $var):DotEnv{
		$var = strtoupper($var);

		if($this->global === true){
			unset($_ENV[$var]);
			putenv($var);
		}

		unset($this->_ENV[$var]);

		return $this;
	}

	/**
	 * use with caution!
	 *
	 * @return \chillerlan\DotEnv\DotEnv
	 */
	public function clear():DotEnv{

		if($this->global === true){
			$_ENV = [];
		}

		$this->_ENV = [];

		return $this;
	}

	/**
	 * @param string $file
	 *
	 * @return array
	 * @throws \chillerlan\DotEnv\DotEnvException
	 */
	protected function read(string $file):array{

		if(!is_file($file) || !is_readable($file)){
			throw new DotEnvException('invalid file: '.$file);
		}

		// Read file into an array of lines with auto-detected line endings
		$autodetect = ini_get('auto_detect_line_endings');
		ini_set('auto_detect_line_endings', '1');
		$lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		ini_set('auto_detect_line_endings', $autodetect);

		if(!is_array($lines) || empty($lines)){
			throw new DotEnvException('error while reading file: '.$file);
		}

		return array_map('trim', $lines);
	}

	/**
	 * @param string[] $data
	 * @param bool     $overwrite
	 *
	 * @return \chillerlan\DotEnv\DotEnv
	 */
	protected function loadData(array $data, bool $overwrite):DotEnv{

		foreach($data as $line){

			// skip empty lines and comments
			if(empty($line) || strpos($line, '#') === 0){
				continue;
			}

			$kv = array_map('trim', explode('=', $line, 2));

			// skip empty and numeric keys, keys with spaces, existing keys that shall not be overwritten
			if(
				empty($kv[0])
				|| is_numeric($kv[0])
				|| strpos($kv[0], ' ') !== false
				|| (!$overwrite && $this->get($kv[0]) !== false)
			){
				continue;
			}

			$this->set($kv[0], isset($kv[1]) ? trim($kv[1]) : null);
		}

		return $this;
	}

	/**
	 * @param string|null $value
	 *
	 * @return string|null
	 */
	protected function parse(string $value = null):?string{

		if($value !== null){

			$q = $value[0] ?? null;

			$value = in_array($q, ["'", '"'], true)
				// handle quoted strings
				? preg_replace("/^$q((?:[^$q\\\\]|\\\\\\\\|\\\\$q)*)$q.*$/mx", '$1', $value)
				// skip inline comments
				: trim(explode('#', $value, 2)[0]);

			// handle multiline values
			$value = implode(PHP_EOL, explode('\\n', $value));

			// handle nested ${VARS}
			if(strpos($value, '$') !== false){
				$value = preg_replace_callback('/\${(?<var>[_a-z\d]+)}/i', fn($matches) => $this->get($matches['var']), $value);
			}

		}

		return $value;
	}

	/**
	 * @param string[]|null $required - case sensitive!
	 *
	 * @return \chillerlan\DotEnv\DotEnv
	 * @throws \chillerlan\DotEnv\DotEnvException
	 */
	protected function check(array $required = null):DotEnv{

		if($required === null || empty($required)){
			return $this;
		}

		foreach($required as $var){
			if(!$this->isset($var)){
				throw new DotEnvException('required variable not set: '.strtoupper($var));
			}
		}

		return $this;
	}

}
