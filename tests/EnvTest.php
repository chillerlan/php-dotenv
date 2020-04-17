<?php
/**
 * Class EnvTest
 *
 * @filesource   EnvTest.php
 * @created      25.11.2017
 * @package      chillerlan\DotEnvTest
 * @author       Smiley <smiley@chillerlan.net>
 * @copyright    2017 Smiley
 * @license      MIT
 */

namespace chillerlan\DotEnvTest;

use chillerlan\DotEnv\{DotEnv, DotEnvException};
use PHPUnit\Framework\TestCase;

class EnvTest extends TestCase{

	protected DotEnv $dotenv;

	protected function setUp():void{
		$this->dotenv = new DotEnv(__DIR__, '.env_test');
	}

	public function testLoadInvalidFile():void{
		$this->expectException(DotEnvException::class);
		$this->expectExceptionMessage('invalid file:');

		(new DotEnv('foo'))->load();
	}

	public function testLoadInvalidReadError():void{
		$this->expectException(DotEnvException::class);
		$this->expectExceptionMessage('error while reading file:');

		(new DotEnv(__DIR__, '.env_error'))->load(); // empty file
	}

	public function testLoadRequiredVarMissing():void{
		$this->expectException(DotEnvException::class);
		$this->expectExceptionMessage('required variable not set: FOO');

		$this->dotenv->load(['foo']);
	}

	public function testAddEnv():void{
		$this->dotenv->addEnv(__DIR__, '.env_test');
		self::assertNull($this->dotenv->get('foo'));

		$this->dotenv->addEnv(__DIR__, '.another_env', true, ['FOO']); // case sensitive here!
		self::assertSame('BAR', $this->dotenv->get('foo'));
	}

	public function testLoadGet():void{
		$this->dotenv->load(['VAR']);

		self::assertFalse(isset($_ENV[42])); // numerical keys shouldn't exist in globals

		self::assertNotEmpty($_ENV); // we're in global mode
		self::asserttrue(isset($_ENV['VAR']));

		self::assertSame('test', $_ENV['VAR']);
		self::assertSame('test', $this->dotenv->get('var'));
		self::assertSame('test', $this->dotenv->get('VAR'));
		self::assertSame('test', $this->dotenv->var);
		self::assertSame('test', $this->dotenv->VAR);
		self::assertSame($_ENV['VAR'], $this->dotenv->get('VAR'));
		self::assertSame($_ENV['VAR'], $this->dotenv->VAR);

		self::assertSame('Oh here\'s some silly &%=ä$&/"§% value', $_ENV['TEST']); // stripped comment line
		self::assertSame('foo'.PHP_EOL.'bar'.PHP_EOL.'nope', $_ENV['MULTILINE']);

		self::assertSame('Hello World!', $_ENV['VAR3']);
		self::assertSame('{$VAR1} $VAR2 {VAR1}', $_ENV['VAR4']); // not resolved
	}

	public function testSetUnsetClear():void{
		$this->dotenv->load();

		self::assertTrue(isset($_ENV['TEST']));
		self::assertTrue(isset($this->dotenv->TEST));
		unset($this->dotenv->TEST);
		self::assertFalse(isset($_ENV['TEST']));
		self::assertFalse($this->dotenv->get('test'));
		self::assertFalse($this->dotenv->test);

		// generic
		$this->dotenv->set('TESTVAR', 'some value: ${var3}');
		self::assertSame('some value: Hello World!', $_ENV['TESTVAR']);
		self::assertSame('some value: Hello World!', $this->dotenv->get('TESTVAR'));

		// magic
		$this->dotenv->TESTVAR = 'some other value: ${var3}';
		self::assertSame('some other value: Hello World!', $_ENV['TESTVAR']);
		self::assertSame('some other value: Hello World!', $this->dotenv->TESTVAR);

		$this->dotenv->clear();

		self::assertSame([], $_ENV);
	}

}
