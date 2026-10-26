<?php declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * The generator form logic is a plain theme script: it is rendered by the
 * browser and cannot be executed here, so its contract is asserted on the
 * source, the same way as for the template markup.
 */
class GeneratorScriptTest extends TestCase
{
	private string $script;

	protected function setUp(): void
	{
		$path = dirname(__DIR__) . '/src/Themes/default/scripts/simple_mod_maker.js';

		$this->assertFileExists($path);

		$this->script = (string) file_get_contents($path);
	}

	public function testTheHookSelectDegradesGracefullyWithoutTheCdnLibrary(): void
	{
		// Without Tom Select the native multiple select rendered by the
		// server must remain the working fallback
		$this->assertMatchesRegularExpression(
			'/typeof TomSelect === "undefined"\s*\|\|\s*typeof smmGenerator === "undefined"\s*\)\s*\{\s*return;/',
			$this->script
		);
	}

	public function testTheSettingsVisibilityIsRestoredFromTheSelectOnLoad(): void
	{
		// Returning to the form with errors does not fire the change event,
		// so the visibility must be initialized without it. The CDN library
		// is only defined after the deferred loading, hence the waiting.
		$this->assertStringContainsString('addEventListener("DOMContentLoaded"', $this->script);
		$this->assertStringContainsString('this.restoreSettingPlacement();', $this->script);
		$this->assertStringContainsString('document.getElementById("settings_area")', $this->script);
		$this->assertStringContainsString('this.changeSettingPlacement(placement.value);', $this->script);
	}

	public function testEveryDataBlockOfTheTemplateIsConsumed(): void
	{
		foreach ([
			'smmGenerator.hookUrl',
			'smmGenerator.minSearchLength',
			'smmGenerator.texts.remove',
			'smmGenerator.texts.addHook',
			'smmGenerator.texts.noMatches',
			'smmGenerator.texts.minSearchLength',
			'smmGenerator.hookList.data',
			'smmGenerator.hookList.items',
			'smmGenerator.skeleton.options',
			'smmGenerator.skeleton.tables',
			'smmGenerator.skeleton.scheduledTasks',
			'smmGenerator.skeleton.backgroundTasks',
			'smmGenerator.skeleton.legacyTasks',
		] as $key) {
			$this->assertStringContainsString($key, $this->script);
		}
	}

	public function testTheAlpineComponentFactoriesAreStillProvided(): void
	{
		$this->assertStringContainsString('post(target) {', $this->script);
		$this->assertStringContainsString('changeSettingPlacement(target) {', $this->script);
		$this->assertStringContainsString('handleOptions() {', $this->script);
		$this->assertStringContainsString('handleTables() {', $this->script);
		$this->assertStringContainsString('handleTasks() {', $this->script);
	}

	public function testColumnsComingFromTheSkeletonAreNormalized(): void
	{
		$start = strpos($this->script, 'handleTables() {');
		$end   = strpos($this->script, 'removeColumn(table_index', (int) $start);
		$body  = substr($this->script, (int) $start, (int) $end - (int) $start);

		$this->assertStringContainsString('init() {', $body);
		$this->assertStringContainsString('this.watchColumn(column)', $body);
	}

	public function testTheScriptLoadsNothingByItself(): void
	{
		// All the external resources are connected by the Handler and the
		// template, so the script stays silent when they are unavailable
		$this->assertStringNotContainsString('cdn.jsdelivr.net', $this->script);
		$this->assertStringNotContainsString('createElement("script")', $this->script);
	}
}
