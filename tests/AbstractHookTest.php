<?php declare(strict_types=1);

namespace Tests;

use ReflectionProperty;
use Bugo\SimpleModMaker\Hooks\AbstractHook;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(AbstractHook::class)]
	#[CoversMethod(AbstractHook::class, '__construct')]
	#[CoversMethod(AbstractHook::class, 'getName')]
	#[CoversMethod(AbstractHook::class, 'getMethodName')]
	#[CoversMethod(AbstractHook::class, 'getMethodName')]
	#[CoversMethod(AbstractHook::class, 'getMethodName')]
	#[CoversMethod(AbstractHook::class, 'getReturnType')]
class AbstractHookTest extends TestCase
{
	private AbstractHook $hook;

	protected function setUp(): void
	{
		$this->hook = new class([], 'TestClass', 'test_class') extends AbstractHook {
			protected function defineName(): string
			{
				return 'test_hook_name';
			}

			public function getParameters(): array
			{
				return [];
			}

			public function getBody(): array
			{
				return [];
			}
		};
	}

	public function testConstructorInitializesProperties(): void
	{
		$context   = ['key' => 'value'];
		$classname = 'TestClassName';
		$snakeName = 'test_snake_name';

		$hook = new class($context, $classname, $snakeName) extends AbstractHook {
			protected function defineName(): string
			{
				return 'test_hook_name';
			}

			public function getParameters(): array
			{
				return [];
			}

			public function getBody(): array
			{
				return [];
			}
		};

		$this->assertEquals('test_hook_name', $hook->getName());

		// Access private properties using reflection for testing
		$contextProperty = new ReflectionProperty($hook, 'context');
		$this->assertEquals($context, $contextProperty->getValue($hook));

		$classnameProperty = new ReflectionProperty($hook, 'classname');
		$this->assertEquals($classname, $classnameProperty->getValue($hook));

		$snakeNameProperty = new ReflectionProperty($hook, 'snakeName');
		$this->assertEquals($snakeName, $snakeNameProperty->getValue($hook));
	}

	public function testGetName(): void
	{
		$this->assertEquals('test_hook_name', $this->hook->getName());
	}

	public function testGetMethodName(): void
	{
		$this->assertEquals('testHookName', $this->hook->getMethodName());
	}

	public function testGetMethodNameWithIntegratePrefix(): void
	{
		$hook = new class([], '', '') extends AbstractHook {
			protected function defineName(): string
			{
				return 'integrate_test_method';
			}

			public function getParameters(): array
			{
				return [];
			}

			public function getBody(): array
			{
				return [];
			}
		};

		$this->assertEquals('testMethod', $hook->getMethodName());
	}

	public function testGetMethodNameWithUnderscores(): void
	{
		$hook = new class([], '', '') extends AbstractHook {
			protected function defineName(): string
			{
				return 'integrate_some_test_method';
			}

			public function getParameters(): array
			{
				return [];
			}

			public function getBody(): array
			{
				return [];
			}
		};

		$this->assertEquals('someTestMethod', $hook->getMethodName());
	}

	public function testGetReturnType(): void
	{
		$this->assertEquals('void', $this->hook->getReturnType());
	}
}
