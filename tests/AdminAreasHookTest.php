<?php declare(strict_types=1);

namespace Tests;

use Bugo\SimpleModMaker\Hooks\AdminAreasHook;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The admin area hook serves three settings areas at once: no area of its own,
 * a subsection of the modification settings, or a section of the forum
 * configuration. Each of them looks different on SMF 2.1 and on SMF 3.0.
 */
#[CoversClass(AdminAreasHook::class)]
	#[CoversMethod(AdminAreasHook::class, 'defineName')]
	#[CoversMethod(AdminAreasHook::class, 'getName')]
	#[CoversMethod(AdminAreasHook::class, 'getMethodName')]
	#[CoversMethod(AdminAreasHook::class, 'getParameters')]
	#[CoversMethod(AdminAreasHook::class, 'getBody')]
	#[CoversMethod(AdminAreasHook::class, 'getDualBody')]
	#[CoversMethod(AdminAreasHook::class, 'getReturnType')]
class AdminAreasHookTest extends TestCase
{
	private AdminAreasHook $hook;

	protected function setUp(): void
	{
		$context = [
			'smm_skeleton' => [
				'settings_area'      => 0,
				'smf_target_version' => '2.1',
				'use_lang_dir'       => false,
			],
		];

		$this->hook = new AdminAreasHook($context, 'TestMod', 'test_mod');
	}

	public function testDefineName(): void
	{
		$this->assertEquals('integrate_admin_areas', $this->hook->getName());
	}

	public function testGetName(): void
	{
		$this->assertEquals('integrate_admin_areas', $this->hook->getName());
	}

	public function testGetMethodName(): void
	{
		$this->assertEquals('adminAreas', $this->hook->getMethodName());
	}

	public function testGetParameters(): void
	{
		$parameters = $this->hook->getParameters();

		$this->assertIsArray($parameters);
		$this->assertArrayHasKey('admin_areas', $parameters);
		$this->assertEquals(['array', true], $parameters['admin_areas']);
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

	#[Test]
	public function areasWithoutOwnPageOnlyLoadTheLanguage(): void
	{
		foreach ([0, 1] as $settingsArea) {
			$hook = $this->hookFor('2.1', $settingsArea);

			$this->assertSame(
				['global $txt;' . "\n", "loadLanguage('Demo');" . "\n"],
				self::eol($hook->getBody()),
				'settings_area=' . $settingsArea
			);
		}
	}

	#[Test]
	public function areaTwoIsASubsectionOfTheModificationSettings(): void
	{
		$legacy = $this->hookFor('2.1', 2);
		$modern = $this->hookFor('3.0', 2);

		$this->assertSame(
			[
				'global $txt;' . "\n",
				"loadLanguage('Demo');" . "\n",
				"\$admin_areas['config']['areas']['modsettings']['subsections']['demo'] = [\$txt['demo_title']];",
			],
			self::eol($legacy->getBody())
		);

		$this->assertSame(
			[
				"Lang::load('Demo');" . "\n",
				"\$admin_areas['config']['areas']['modsettings']['subsections']['demo'] = [Lang::\$txt['demo_title']];",
			],
			self::eol($modern->getBody())
		);
	}

	#[Test]
	public function areaThreeIsASectionOfTheForumConfiguration(): void
	{
		$legacy = $this->hookFor('2.1', 3);
		$modern = $this->hookFor('3.0', 3);

		$this->assertSame(
			['global $txt;' . "\n", "loadLanguage('Demo');" . "\n", self::areaBlock('$txt')],
			self::eol($legacy->getBody())
		);

		$this->assertSame(
			["Lang::load('Demo');" . "\n", self::areaBlock('Lang::$txt')],
			self::eol($modern->getBody())
		);
	}

	#[Test]
	public function languageDirectoryChangesTheIncludePath(): void
	{
		$legacy = $this->hookFor('2.1', 0, true);
		$modern = $this->hookFor('3.0', 0, true);

		$this->assertSame('loadLanguage(\'Demo/\');' . "\n", self::eol($legacy->getBody())[1]);
		$this->assertSame('Lang::load(\'Demo/\');' . "\n", self::eol($modern->getBody())[0]);
	}

	#[Test]
	public function mixedPackagesGetBothVariantsOfTheArea(): void
	{
		$hook = $this->hookFor('2.1', 3);
		$dual = $hook->getDualBody();

		$this->assertNotNull($dual);

		$this->assertSame(
			['global $txt;' . "\n", "loadLanguage('Demo');" . "\n", self::areaBlock('$txt')],
			self::eol($dual['legacy'])
		);

		$this->assertSame(
			["Lang::load('Demo');" . "\n", self::areaBlock('Lang::$txt')],
			self::eol($dual['modern'])
		);
	}

	private function hookFor(string $targetVersion, int $settingsArea, bool $useLangDir = false): AdminAreasHook
	{
		return new AdminAreasHook([
			'smm_skeleton' => [
				'settings_area'      => $settingsArea,
				'smf_target_version' => $targetVersion,
				'use_lang_dir'       => $useLangDir,
			],
		], 'Demo', 'demo');
	}

	/**
	 * The declaration of an own admin section, with the language array passed in
	 * as its legacy or modern form.
	 */
	private static function areaBlock(string $lang): string
	{
		return "\$admin_areas['config']['areas']['demo'] = [\n"
			. "\t'label'       => {$lang}['demo_title'],\n"
			. "\t'function'    => [\$this, 'settings'],\n"
			. "\t// .main_icons demo\n"
			. "\t'icon'        => 'demo',\n"
			. "\t'subsections' => [\n"
			. "\t\t'section1' => [{$lang}['demo_section1_title']],\n"
			. "\t\t//'section2' => [{$lang}['demo_section2_title']],\n"
			. "\t]\n"
			. '];';
	}

	/**
	 * The generated bodies keep PHP_EOL as their line separator, so the
	 * platform-specific form is reduced to the common one before the comparison.
	 *
	 * @param list<string> $lines
	 * @return list<string>
	 */
	private static function eol(array $lines): array
	{
		return array_map(static fn (string $line): string => str_replace(PHP_EOL, "\n", $line), $lines);
	}
}
