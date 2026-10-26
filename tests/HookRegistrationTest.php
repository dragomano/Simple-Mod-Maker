<?php declare(strict_types=1);

namespace Tests;

use Bugo\SimpleModMaker\Hooks\HookFactory;
use Bugo\SimpleModMaker\Hooks\HookInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(HookFactory::class)]
class HookRegistrationTest extends TestCase
{
	#[Test]
	public function everyHandlerIsRegisteredInTheFactory(): void
	{
		$handlers = $this->getHandlers();

		$this->assertNotEmpty($handlers);

		foreach ($handlers as $classname => $hook) {
			$name = $hook->getName();

			$this->assertInstanceOf(
				$hook::class,
				HookFactory::create($name),
				"The '$classname' handler must be registered in the factory under '$name'"
			);
		}
	}

	#[Test]
	public function hookNamesOfAllHandlersAreUnique(): void
	{
		$names = array_map(fn(HookInterface $hook): string => $hook->getName(), $this->getHandlers());

		$this->assertSame($names, array_unique($names), 'Two handlers must not serve the same hook');
	}

	/**
	 * All the handlers found in the Hooks directory, indexed by short class name
	 *
	 * @return array<string, HookInterface>
	 */
	private function getHandlers(): array
	{
		$handlers = [];

		foreach (glob(dirname(__DIR__) . '/src/Sources/SimpleModMaker/Hooks/*Hook.php') ?: [] as $file) {
			$classname = basename($file, '.php');

			// The abstract base is not a concrete handler of any hook
			if ($classname === 'AbstractHook') {
				continue;
			}

			$fqcn = 'Bugo\\SimpleModMaker\\Hooks\\' . $classname;

			$this->assertTrue(class_exists($fqcn), "The '$file' file must contain the '$fqcn' class");

			$handlers[$classname] = new $fqcn([], '', '');
		}

		return $handlers;
	}
}
