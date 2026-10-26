<?php declare(strict_types=1);

namespace Tests;

use Bugo\SimpleModMaker\Builder;
use Exception;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;

/**
 * The working folder in Packages is kept after a successful build on purpose
 * (it allows to install the mod right away), so the cleanup must only affect
 * a failed build and the temporary archive files.
 */
class BuilderCleanupTest extends TestCase
{
	/** @var list<string> */
	private array $tempDirs = [];

	public function testBuildsWithTheSameVersionDoNotInterfere(): void
	{
		$base   = $this->makeBaseDir();
		$first  = $this->build($base, ['make_css' => true]);
		$second = $this->build($base, ['make_script' => true]);

		$this->assertFileExists($first . '/Themes/default/css/demo.css');
		$this->assertFileExists($second . '/Themes/default/scripts/demo.js');

		// Each build keeps only its own files
		$this->assertFileDoesNotExist($first . '/Themes/default/scripts/demo.js');
		$this->assertFileDoesNotExist($second . '/Themes/default/css/demo.css');

		$this->assertFileExists($first . '/Sources/Class-Demo.php');
		$this->assertFileExists($second . '/Sources/Class-Demo.php');
		$this->assertCount(2, array_diff(scandir($base) ?: [], ['.', '..']));
	}

	public function testFailedCreateThrows(): void
	{
		$base = $this->makeBaseDir();

		// A file instead of the build directory makes mktree() fail
		touch($base . '/blocked');

		$builder = $this->builder($base . '/blocked/demo');

		$this->expectException(Exception::class);

		$builder->create('<?php');
	}

	public function testFailedCreateLeavesNoPartialFiles(): void
	{
		$base = $this->makeBaseDir();

		touch($base . '/blocked');

		$builder = $this->builder($base . '/blocked/demo');

		try {
			$builder->create('<?php');
		} catch (Exception) {
			// The file system rejected the build
		}

		// Neither the build folder nor the pre-existing file were touched
		$this->assertSame(['blocked'], array_values(array_diff(scandir($base) ?: [], ['.', '..'])));
	}

	public function testCreatePackageFailureLeavesNoTemporaryArchives(): void
	{
		$base = $this->makeBaseDir();
		$path = $base . '/demo_1.0_' . bin2hex(random_bytes(8));

		$this->builder($path)->create('<?php');

		package_flush_cache();

		// Without the working folder the packaging stage fails
		self::deleteDirectory($path);

		$tempFiles = fn(): array => glob(sys_get_temp_dir() . DIRECTORY_SEPARATOR . '*_smf21*') ?: [];

		$before = $tempFiles();

		$thrown = false;

		try {
			$this->builder($path)->createPackage();
		} catch (RuntimeException) {
			// fatal_error is stubbed in Bootstrap
			$thrown = true;
		}

		$this->assertTrue($thrown, 'The packaging failure must be reported.');
		$this->assertSame($before, $tempFiles());
	}

	public function testRemoveArchiveDeletesBothArchiveFiles(): void
	{
		$builder = $this->builder($this->makeBaseDir() . '/demo_1.0_' . bin2hex(random_bytes(8)));

		$zip = sys_get_temp_dir() . DIRECTORY_SEPARATOR . bin2hex(random_bytes(5)) . '_smf21.zip';
		$tmp = str_replace('.zip', '.tmp', $zip);

		file_put_contents($zip, 'zip');
		file_put_contents($tmp, 'tmp');

		$property = new ReflectionProperty($builder, 'archive');
		$property->setValue($builder, $zip);

		(new ReflectionMethod($builder, 'removeArchive'))->invoke($builder);

		$this->assertFileDoesNotExist($zip);
		$this->assertFileDoesNotExist($tmp);
	}

	public function testCreateArchiveReturnsReadyZipAndKeepsWorkingFolder(): void
	{
		$base = $this->makeBaseDir();
		$path = $base . '/demo_1.0_' . bin2hex(random_bytes(8));

		$builder = $this->builder($path);

		$builder->create('<?php');

		package_flush_cache();

		$archive = (new ReflectionMethod($builder, 'createArchive'))->invoke($builder);

		$this->assertFileExists($archive);
		$this->assertGreaterThan(0, filesize($archive));

		// The built package stays in Packages for immediate installation
		$this->assertDirectoryExists($path);

		// Release the test archives the same way the production code does
		(new ReflectionMethod($builder, 'removeArchive'))->invoke($builder);

		$this->assertFileDoesNotExist($archive);
		$this->assertFileDoesNotExist(str_replace('.zip', '.tmp', $archive));
	}

	private function build(string $base, array $overrides = []): string
	{
		$path = $base . '/demo_1.0_' . bin2hex(random_bytes(8));

		$this->builder($path, $overrides)->create('<?php');

		return $path;
	}

	private function builder(string $path, array $overrides = []): Builder
	{
		return new Builder([
			'skeleton'  => $this->skeleton($overrides),
			'classname' => 'Demo',
			'snakename' => 'demo',
			'path'      => $path,
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
			'languages'          => ['english'],
			'title'              => ['english' => 'Demo'],
			'description'        => ['english' => 'Desc'],
		], $overrides);
	}

	private function makeBaseDir(): string
	{
		$base = sys_get_temp_dir() . '/smm_cleanup_' . bin2hex(random_bytes(4));

		mkdir($base, 0777, true);

		$this->tempDirs[] = $base;

		return $base;
	}

	protected function tearDown(): void
	{
		// The package cache is global, so its leftovers must not reach other tests
		$GLOBALS['package_cache'] = [];

		foreach ($this->tempDirs as $dir) {
			self::deleteDirectory($dir);
		}

		$this->tempDirs = [];
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
