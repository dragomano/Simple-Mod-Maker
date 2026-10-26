<?php declare(strict_types=1);

namespace Tests;

use Bugo\SimpleModMaker\Hooks\ActionsHook;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(ActionsHook::class)]
	#[CoversMethod(ActionsHook::class, 'getName')]
	#[CoversMethod(ActionsHook::class, 'getMethodName')]
	#[CoversMethod(ActionsHook::class, 'getParameters')]
	#[CoversMethod(ActionsHook::class, 'getBody')]
	#[CoversMethod(ActionsHook::class, 'getReturnType')]
class ActionsHookTest extends TestCase
{
	private ActionsHook $hook;

	protected function setUp(): void
	{
		$this->hook = new ActionsHook([], '', '');
	}

	public function testGetName(): void
	{
		$this->assertEquals('integrate_actions', $this->hook->getName());
	}

	public function testGetMethodName(): void
	{
		$this->assertEquals('actions', $this->hook->getMethodName());
	}

	public function testGetParameters(): void
	{
		$parameters = $this->hook->getParameters();

		$this->assertIsArray($parameters);
		$this->assertArrayHasKey('actions', $parameters);
		$this->assertEquals(['array', true], $parameters['actions']);
	}

	public function testGetBody(): void
	{
		$body = $this->hook->getBody();

		$this->assertIsArray($body);
		$this->assertContains("\$actions['example_action'] = [false, [\$this, 'exampleMethod']];", $body);
	}

	public function testGetReturnType(): void
	{
		$this->assertEquals('void', $this->hook->getReturnType());
	}
}
