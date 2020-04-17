<?php
/**
 * Class EnvTestNonGlobal
 *
 * @filesource   EnvTestNonGlobal.php
 * @created      03.01.2018
 * @package      chillerlan\DotEnvTest
 * @author       Smiley <smiley@chillerlan.net>
 * @copyright    2018 Smiley
 * @license      MIT
 */

namespace chillerlan\DotEnvTest;

use chillerlan\DotEnv\DotEnv;

class EnvTestNonGlobal extends EnvTest{

	protected function setUp():void{
		$this->dotenv = new DotEnv(__DIR__, '.env_test', false);
	}

	public function testLoadGet():void{
		$this->dotenv->load(['VAR']);

		self::assertFalse(isset($_ENV[42])); // numerical keys shouldn't exist in globals


		self::assertSame([], $_ENV); // we're in non-global mode
		self::assertFalse(isset($_ENV['VAR']));

		self::assertSame('test', $this->dotenv->get('var'));
		self::assertSame('test', $this->dotenv->get('VAR'));

		self::assertSame('Oh here\'s some silly &%=ä$&/"§% value', $this->dotenv->get('TEST')); // stripped comment line
		self::assertSame('foo'.PHP_EOL.'bar'.PHP_EOL.'nope', $this->dotenv->get('MULTILINE'));

		self::assertSame('Hello World!', $this->dotenv->get('VAR3'));
		self::assertSame('{$VAR1} $VAR2 {VAR1}', $this->dotenv->get('VAR4')); // not resolved
	}

	public function testSetUnsetClear():void{
		$this->dotenv->load();

		self::assertFalse(isset($_ENV['TEST']));
		self::assertTrue($this->dotenv->isset('TEST'));
		unset($this->dotenv->TEST);
		self::assertFalse(isset($_ENV['TEST']));
		self::assertNull($this->dotenv->get('test'));

		// generic
		$this->dotenv->set('TESTVAR', 'some value: ${var3}');
		self::assertSame('some value: Hello World!', $this->dotenv->get('TESTVAR'));

		// magic
		$this->dotenv->TESTVAR = 'some other value: ${var3}';
		self::assertSame('some other value: Hello World!', $this->dotenv->TESTVAR);

		$this->dotenv->clear();

		self::assertSame([], $_ENV);
	}

}
