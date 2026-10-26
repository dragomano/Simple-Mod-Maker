<?php declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * The logic of the generator form lives in the theme script now, so the
 * template must only render the markup and one serialized data object.
 */
class TemplateGeneratorScriptTest extends TestCase
{
	private string $template;

	protected function setUp(): void
	{
		$path = dirname(__DIR__) . '/src/Themes/default/SimpleModMaker.template.php';

		$this->assertFileExists($path);

		// The markup lives in PHP single-quoted strings: \' and \\ are escapes.
		$this->template = strtr((string) file_get_contents($path), ["\\'" => "'", '\\\\' => '\\']);
	}

	public function testTheFormLogicIsOutsourcedToTheLocalScript(): void
	{
		$this->assertStringNotContainsString('new TomSelect', $this->template);
		$this->assertStringNotContainsString('changeSettingPlacement(', $this->template);
		$this->assertStringNotContainsString('handleOptions() {', $this->template);
		$this->assertStringNotContainsString('handleTables() {', $this->template);
		$this->assertStringNotContainsString('handleTasks() {', $this->template);
	}

	public function testTheDataScriptFeedsTheLocalScriptThroughOneSerializerCall(): void
	{
		$this->assertStringContainsString('window.smmGenerator = ', $this->template);
		$this->assertStringContainsString('Serializer::json($smm_data)', $this->template);
	}

	public function testAlpineStillComesFromTheCdn(): void
	{
		$this->assertStringContainsString('https://cdn.jsdelivr.net/npm/alpinejs@3/dist/cdn.min.js', $this->template);
		$this->assertStringContainsString('window.Alpine === undefined', $this->template);
	}

	public function testTheFormStillKeepsItsNativeSubmitHelpers(): void
	{
		$this->assertStringContainsString('onsubmit="submitonce(this);"', $this->template);
		$this->assertStringContainsString('@click="smm.post($root)"', $this->template);
	}
}
