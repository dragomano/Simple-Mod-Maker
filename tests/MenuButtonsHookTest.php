<?php declare(strict_types=1);

namespace Tests;

use Bugo\SimpleModMaker\Hooks\MenuButtonsHook;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(MenuButtonsHook::class)]
	#[CoversMethod(MenuButtonsHook::class, 'defineName')]
	#[CoversMethod(MenuButtonsHook::class, 'getName')]
	#[CoversMethod(MenuButtonsHook::class, 'getMethodName')]
	#[CoversMethod(MenuButtonsHook::class, 'getParameters')]
	#[CoversMethod(MenuButtonsHook::class, 'getBody')]
	#[CoversMethod(MenuButtonsHook::class, 'getReturnType')]
class MenuButtonsHookTest extends TestCase
{
	private MenuButtonsHook $hook;

	protected function setUp(): void
	{
		$this->hook = new MenuButtonsHook([], '', '');
	}

	public function testDefineName(): void
	{
		$this->assertEquals('integrate_menu_buttons', $this->hook->getName());
	}

	public function testGetName(): void
	{
		$this->assertEquals('integrate_menu_buttons', $this->hook->getName());
	}

	public function testGetMethodName(): void
	{
		$this->assertEquals('menuButtons', $this->hook->getMethodName());
	}

	public function testGetParameters(): void
	{
		$parameters = $this->hook->getParameters();

		$this->assertIsArray($parameters);
		$this->assertArrayHasKey('buttons', $parameters);
		$this->assertEquals(['array', true], $parameters['buttons']);
	}

	public function testGetBody(): void
	{
		$body = $this->hook->getBody();

		$this->assertIsArray($body);
		$this->assertNotEmpty($body);
	}

	public function testGetReturnType(): void
	{
		$this->assertEquals('void', $this->hook->getReturnType());
	}
}
