<?php declare(strict_types=1);

namespace Tests;

use Bugo\SimpleModMaker\Builder;
use FilesystemIterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionMethod;

/**
 * Every language offered by the forum must get a complete language file: the
 * generated code references the base keys unconditionally, whatever
 * translations are filled in and whatever readmes exist.
 */
#[CoversClass(Builder::class)]
class BuilderLangFilesTest extends TestCase
{
	/** @var list<string> */
	private array $tempDirs = [];

	protected function tearDown(): void
	{
		foreach ($this->tempDirs as $dir) {
			self::deleteDirectory($dir);
		}

		$this->tempDirs = [];
	}

	#[Test]
	public function basicModWithoutSettingsCreatesNoLanguageFiles(): void
	{
		$base    = $this->makeTempDir();
		$builder = $this->makeBuilder($base, [
			'title'       => [],
			'description' => [],
		]);

		$builder->create('<?php');
		package_flush_cache();

		$this->assertDirectoryDoesNotExist($base . '/demo/Themes/default/languages');
	}

	#[Test]
	public function emptyTranslationsStillProduceCompleteLanguageFiles(): void
	{
		$base    = $this->makeTempDir();
		$builder = $this->makeBuilder($base, [
			'settings_area' => 2,
			'languages'     => ['english'],
			'title'         => [],
			'description'   => [],
		]);

		$builder->create('<?php');
		package_flush_cache();

		$langFile = $base . '/demo/Themes/default/languages/Demo.english.php';

		$this->assertFileExists($langFile);
		$this->lintFile($langFile);

		$txt = [];

		include $langFile;

		$this->assertSame('Demo Mod', $txt['demo_title']);
		$this->assertSame('', $txt['demo_description']);

		// A language without any filled string is not shipped at all:
		// SMF falls back to the english file when loading
		$this->assertFileDoesNotExist($base . '/demo/Themes/default/languages/Demo.russian.php');
	}

	#[Test]
	public function sectionTitlesExistEvenWithoutDescriptions(): void
	{
		$base    = $this->makeTempDir();
		$builder = $this->makeBuilder($base, [
			'settings_area' => 3,
			'languages'     => ['english'],
			'title'         => [],
			'description'   => [],
		]);

		$builder->create('<?php');
		package_flush_cache();

		$txt = [];

		include $base . '/demo/Themes/default/languages/Demo.english.php';

		$this->assertSame('Section 1', $txt['demo_section1_title']);
		$this->assertSame('Section 2', $txt['demo_section2_title']);
	}

	#[Test]
	public function manifestListsCreatedLanguageFilesIndependentlyOfReadmes(): void
	{
		$base    = $this->makeTempDir();
		$builder = $this->makeBuilder($base, [
			'settings_area' => 2,
			'make_readme'   => false,
			'readmes'       => [],
			'languages'     => ['english', 'russian'],
			'title'         => [],
			'description'   => [],
			'options'       => [
				0 => [
					'name'         => 'enable_thing',
					'type'         => 'check',
					'default'      => '',
					'variants'     => '',
					'translations' => ['russian' => 'Вкл'],
				],
			],
		]);

		$builder->create('<?php');

		$method = new ReflectionMethod($builder, 'preparePackageInfo');
		$method->invoke($builder);

		package_flush_cache();

		$manifest = (string) file_get_contents($base . '/demo/package-info.xml');

		$this->assertStringContainsString('Themes/default/languages/Demo.english.php', $manifest);
		$this->assertStringContainsString('Themes/default/languages/Demo.russian.php', $manifest);
		$this->assertStringNotContainsString('readme/', $manifest);
	}

	#[Test]
	public function optionAndTaskKeysFallBackToEnglishOrName(): void
	{
		$base    = $this->makeTempDir();
		$builder = $this->makeBuilder($base, [
			'settings_area' => 1,
			'title'         => [],
			'description'   => [],
			'options'       => [
				0 => [
					'name'         => 'enable_thing',
					'type'         => 'check',
					'default'      => '',
					'variants'     => '',
					'translations' => ['russian' => 'Вкл'],
				],
			],
			'scheduled_tasks' => [
				0 => [
					'slug'         => 'cleanup',
					'names'        => ['english' => 'Cleanup'],
					'descriptions' => [],
					'regularity'   => 0,
				],
			],
		]);

		$builder->create('<?php');
		package_flush_cache();

		$txt = [];

		include $base . '/demo/Themes/default/languages/Demo.english.php';

		$this->assertSame('enable_thing', $txt['demo_enable_thing']);
		$this->assertSame('Cleanup', $txt['scheduled_task_cleanup']);
		$this->assertSame('', $txt['scheduled_task_desc_cleanup']);

		$txt = [];

		include $base . '/demo/Themes/default/languages/Demo.russian.php';

		$this->assertSame('Вкл', $txt['demo_enable_thing']);
		$this->assertSame('Cleanup', $txt['scheduled_task_cleanup']);
		$this->assertSame('', $txt['scheduled_task_desc_cleanup']);
	}

	private function makeBuilder(string $base, array $overrides): Builder
	{
		return new Builder([
			'skeleton'  => $this->skeleton($overrides),
			'classname' => 'Demo',
			'snakename' => 'demo',
			'path'      => $base . '/demo',
		]);
	}

	private function skeleton(array $overrides = []): array
	{
		return array_merge([
			'name'               => 'Demo Mod',
			'filename'           => 'Class-Demo',
			'hooks'              => [],
			'author'             => 'Bugo',
			'namespace'          => 'Bugo',
			'email'              => 'bugo@example.test',
			'readmes'            => ['english' => 'Text'],
			'version'            => '1.0',
			'site'               => 'https://example.test',
			'settings_area'      => 0,
			'license'            => 'mit',
			'license_data'       => ['link' => 'https://x', 'full_name' => 'MIT'],
			'make_dir'           => false,
			'use_lang_dir'       => false,
			'make_template'      => false,
			'make_script'        => false,
			'make_css'           => false,
			'make_readme'        => false,
			'add_copyrights'     => false,
			'min_php_version'    => '',
			'smf_target_version' => '2.1',
			'callbacks'          => [],
			'options'            => [],
			'tables'             => [],
			'scheduled_tasks'    => [],
			'background_tasks'   => [],
			'legacy_tasks'       => [],
			'languages'          => ['english', 'russian'],
			'title'              => ['english' => 'Demo'],
			'description'        => ['english' => 'Desc'],
		], $overrides);
	}

	private function makeTempDir(): string
	{
		$dir = sys_get_temp_dir() . '/smm_langs_' . bin2hex(random_bytes(4));

		mkdir($dir, 0777, true);

		$this->tempDirs[] = $dir;

		return $dir;
	}

	private function lintFile(string $file): void
	{
		$temp = tempnam(sys_get_temp_dir(), 'smm_lint_') . '.php';

		try {
			file_put_contents($temp, file_get_contents($file));

			exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($temp) . ' 2>&1', $output, $exitCode);

			$this->assertSame(0, $exitCode, implode("\n", $output));
		} finally {
			@unlink($temp);
		}
	}

	private static function deleteDirectory(string $path): void
	{
		if (! is_dir($path)) {
			return;
		}

		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ($items as $item) {
			$item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
		}

		@rmdir($path);
	}
}
