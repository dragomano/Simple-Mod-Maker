<?php declare(strict_types=1);

namespace Tests;

use Bugo\SimpleModMaker\Handler;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

#[CoversMethod(Handler::class, 'loadAssets')]
class HandlerAssetsTest extends TestCase
{
	protected function setUp(): void
	{
		$GLOBALS['smm_assets'] = [];
	}

	public function testAssetsAreQueuedInTheExpectedOrder(): void
	{
		(new ReflectionMethod(Handler::class, 'loadAssets'))->invoke(new Handler());

		$this->assertSame([
			['https://cdn.jsdelivr.net/npm/tom-select@2/dist/js/tom-select.complete.min.js', ['external' => true, 'defer' => true]],
			['simple_mod_maker.js', []],
		], $GLOBALS['smm_assets']['js']);

		$this->assertSame([
			['https://cdn.jsdelivr.net/npm/tom-select@2/dist/css/tom-select.min.css', ['external' => true]],
			['simple_mod_maker.css', []],
		], $GLOBALS['smm_assets']['css']);
	}

	public function testTheCdnLibraryIsDeferredAndTheLocalLogicIsNot(): void
	{
		(new ReflectionMethod(Handler::class, 'loadAssets'))->invoke(new Handler());

		// The CDN library must not block the rendering of the form, while
		// the local logic defines the smm helper during parsing: deferred
		// scripts that start Alpine (the Light Portal bundle) must see it
		$this->assertTrue($GLOBALS['smm_assets']['js'][0][1]['defer']);
		$this->assertArrayNotHasKey('defer', $GLOBALS['smm_assets']['js'][1][1]);
	}

	public function testTheLocalGeneratorScriptShipsWithTheTheme(): void
	{
		$this->assertFileExists(dirname(__DIR__) . '/src/Themes/default/scripts/simple_mod_maker.js');
	}
}
