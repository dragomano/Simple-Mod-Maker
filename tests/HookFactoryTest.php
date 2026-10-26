<?php declare(strict_types=1);

namespace Tests;

use Bugo\SimpleModMaker\Hooks\HookFactory;
use Bugo\SimpleModMaker\Hooks\HookInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(HookFactory::class)]
	#[CoversMethod(HookFactory::class, 'create')]
	#[CoversMethod(HookFactory::class, 'getAvailableHooks')]
	#[CoversMethod(HookFactory::class, 'create')]
class HookFactoryTest extends TestCase
{
	public function testAllAvailableHooksCanBeCreated(): void
	{
		$availableHooks = HookFactory::getAvailableHooks();

		foreach ($availableHooks as $hookName) {
			$hook = HookFactory::create($hookName);

			$this->assertNotNull($hook, "Hook '$hookName' should be created successfully");
			$this->assertInstanceOf(HookInterface::class, $hook, "Hook '$hookName' should implement HookInterface");
			$this->assertEquals($hookName, $hook->getName(), "Hook '$hookName' should have correct name");
			$this->assertNotEmpty($hook->getMethodName(), "Hook '$hookName' should have a valid method name");
		}
	}

	public function testCreateNonExistentHook(): void
	{
		$hook = HookFactory::create('non_existent_hook');

		$this->assertNull($hook);
	}
}
