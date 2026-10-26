<?php declare(strict_types=1);

namespace Tests;

use Bugo\SimpleModMaker\Hooks\BbcCodesHook;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;

#[CoversClass(BbcCodesHook::class)]
	#[CoversMethod(BbcCodesHook::class, 'getName')]
	#[CoversMethod(BbcCodesHook::class, 'getMethodName')]
	#[CoversMethod(BbcCodesHook::class, 'getParameters')]
	#[CoversMethod(BbcCodesHook::class, 'getBody')]
	#[CoversMethod(BbcCodesHook::class, 'getReturnType')]
class BbcCodesHookTest extends TestCase
{
	private BbcCodesHook $hook;

	protected function setUp(): void
	{
		$this->hook = new BbcCodesHook([], '', '');
	}

	public function testGetName(): void
	{
		$this->assertEquals('integrate_bbc_codes', $this->hook->getName());
	}

	public function testGetMethodName(): void
	{
		$this->assertEquals('bbcCodes', $this->hook->getMethodName());
	}

	public function testGetParameters(): void
	{
		$parameters = $this->hook->getParameters();

		$this->assertIsArray($parameters);
		$this->assertArrayHasKey('codes', $parameters);
		$this->assertArrayHasKey('no_autolink_tags', $parameters);
		$this->assertEquals(['array', true], $parameters['codes']);
		$this->assertEquals(['array', true], $parameters['no_autolink_tags']);
	}

	public function testGetBody(): void
	{
		$body = $this->hook->getBody();

		$this->assertIsArray($body);
		$this->assertEmpty($body);
	}

	public function testGetReturnType(): void
	{
		$this->assertEquals('void', $this->hook->getReturnType());
	}
}
