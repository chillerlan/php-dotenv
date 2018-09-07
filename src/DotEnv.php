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
 */

namespace chillerlan\DotEnv;

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
	 *
	 * @var array
	 */
	protected $_ENV = [];

	/**
	 * Sets the global $_ENV if true. Otherwise all variables are being kept internally
	 * in $this->_ENV to avoid leaking, making them only accessible via DotEnv::get().
	 *
	 * @var bool
	 */
	protected $global;

	/**
	 * @var string
	 */
	protected $path;

	/**
	 * @var string
	 */
	protected $filename;

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
	 * @throws \Exception
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
	 * @throws \Exception
	 */
	public function loadEnv(string $path, string $filename = null, bool $overwrite = null, array $required = null, bool $global = null):DotEnv{
		$this->global = $global;
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
				/** @noinspection PhpComposerExtensionStubsInspection */
				$env = apache_getenv($var);
			}
			// @codeCoverageIgnoreEnd

		}

		return $env ?? $this->_ENV[$var] ?? null;
	}

	/**
	 * @param string $var
	 * @param string $value
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
				/** @noinspection PhpComposerExtensionStubsInspection */
				apache_setenv($var, $value);
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
		return
			($this->global && (
					isset($_ENV[$var])
					|| getenv($var)
					|| (function_exists('apache_getenv') && apache_getenv($var))
				))
			|| array_key_exists($var, $this->_ENV);
	}

	/**
	 * @param string $var
	 *
	 * @return $this
	 */
	public function unset(string $var){
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
	 * @return $this
	 */
	public function clear(){

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
	 * @throws \Exception
	 */
	protected function read(string $file):array{

		if(!is_readable($file) || !is_file($file)){
			throw new \Exception('invalid file: '.$file);
		}

		// Read file into an array of lines with auto-detected line endings
		$autodetect = ini_get('auto_detect_line_endings');
		ini_set('auto_detect_line_endings', '1');
		$lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
		ini_set('auto_detect_line_endings', $autodetect);

		if(!is_array($lines) || empty($lines)){
			throw new \Exception('error while reading file: '.$file);
		}

		return array_map('trim', $lines);
	}

	/**
	 * @param string[] $data
	 * @param bool     $overwrite
	 *
	 * @return $this
	 */
	protected function loadData(array $data, bool $overwrite){

		foreach($data as $line){

			// skip empty lines and comments
			if(empty($line) || strpos($line, '#') === 0){
				continue;
			}

			$kv = array_map('trim', explode('=', $line, 2));

			// skip empty and numeric keys, keys with spaces, existing keys that shall not be overwritten
			if(empty($kv[0]) || is_numeric($kv[0]) || strpos($kv[0], ' ') !== false || (!$overwrite && $this->get($kv[0]) !== false)){
				continue;
			}

			$this->set($kv[0], isset($kv[1]) ? trim($kv[1]) : null);
		}

		return $this;
	}

	/**
	 * @param string $value
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
				$value = preg_replace_callback('/\${(?<var>[_a-z\d]+)}/i', function($matches){
					return $this->get($matches['var']);
				}, $value);
			}

		}

		return $value;
	}

	/**
	 * @param string[]|null $required - case sensitive!
	 *
	 * @return $this
	 * @throws \Exception
	 */
	protected function check(array $required = null){

		if($required === null || empty($required)){
			return $this;
		}

		foreach($required as $var){
			if(!$this->isset($var)){
				throw new \Exception('required variable not set: '.strtoupper($var));
			}
		}

		return $this;
	}

}
