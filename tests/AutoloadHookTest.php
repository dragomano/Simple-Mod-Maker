<?php declare(strict_types=1);

namespace Tests;

use Bugo\SimpleModMaker\Hooks\AutoloadHook;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The autoload hook maps the namespace of the mod onto the directory layout of
 * its sources: the mapping only makes sense when the mod was built with its own
 * classes directory.
 */
#[CoversClass(AutoloadHook::class)]
class AutoloadHookTest extends TestCase
{
	#[Test]
	public function flatLayoutKeepsTheClassMapHintCommented(): void
	{
		$hook = $this->hook(false);

		$this->assertSame('integrate_autoload', $hook->getName());
		$this->assertSame('autoload', $hook->getMethodName());
		$this->assertSame(['classMap' => ['array', true]], $hook->getParameters());
		$this->assertSame('void', $hook->getReturnType());

		$this->assertSame(
			["// \$classMap['YourNamespace\\\\YourClass\\\\'] = 'YourClass/';"],
			$hook->getBody()
		);

		// The single body serves both supported SMF versions
		$this->assertNull($hook->getDualBody());
	}

	#[Test]
	public function directoryLayoutRegistersTheNamespaceOfTheMod(): void
	{
		$hook = $this->hook(true);

		$this->assertSame(
			["\$classMap['Bugo\\\\Demo\\\\Demo\\\\'] = 'Demo/';"],
			$hook->getBody()
		);
	}

	private function hook(bool $makeDir): AutoloadHook
	{
		return new AutoloadHook([
			'smm_skeleton' => [
				'make_dir'  => $makeDir,
				'namespace' => 'Bugo\\Demo',
			],
		], 'Demo', 'demo');
	}
}
