<?php declare(strict_types=1);

namespace Tests;

use stdClass;
use Bugo\SimpleModMaker\Builder;
use FilesystemIterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionMethod;
use SMF\Db\DatabaseApi;

/**
 * A package must match the SMF version(s) it declares: the installer speaks
 * the database API of the target version, the redirect leads to the settings
 * page of the chosen area, and the archive name names the supported versions.
 */
#[CoversClass(Builder::class)]
class BuilderVersionsTest extends TestCase
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
	public function redirectUrlMatchesSettingsArea(): void
	{
		$expected = [
			1 => '?action=admin;area=modsettings;sa=general',
			2 => '?action=admin;area=modsettings;sa=demo',
			3 => '?action=admin;area=demo',
		];

		foreach ($expected as $area => $url) {
			$base    = $this->makeTempDir();
			$builder = $this->makeBuilder($base, [
				'settings_area' => $area,
				'options'       => [
					0 => [
						'name'         => 'enable_thing',
						'type'         => 'check',
						'default'      => '',
						'variants'     => '',
						'translations' => [],
					],
				],
			]);

			$builder->create('<?php');

			$method = new ReflectionMethod($builder, 'preparePackageInfo');
			$method->invoke($builder);

			package_flush_cache();

			$manifest = (string) file_get_contents($base . '/demo/package-info.xml');

			$this->assertStringContainsString('<redirect url="' . $url . '"', $manifest);
		}
	}

	#[Test]
	public function archiveSuffixFollowsTargetVersion(): void
	{
		$expected = [
			'2.1'     => '_smf21',
			'3.0'     => '_smf30',
			'2.1/3.0' => '_smf21_smf30',
		];

		foreach ($expected as $target => $suffix) {
			$base    = $this->makeTempDir();
			$builder = $this->makeBuilder($base, [
				'smf_target_version' => $target,
			]);

			$method = new ReflectionMethod($builder, 'getVersionSuffix');

			$this->assertSame($suffix, $method->invoke($builder));
		}
	}

	#[Test]
	public function smf3TargetDatabaseSpeaksNewDatabaseApi(): void
	{
		require_once __DIR__ . '/Smf3Stubs.php';

		$base    = $this->makeTempDir();
		$builder = $this->makeBuilder($base, [
			'smf_target_version' => '3.0',
			'tables'             => $this->tables(),
			'scheduled_tasks'    => $this->scheduledTasks(),
		]);

		$builder->create('<?php');
		package_flush_cache();

		$databaseFile = $base . '/demo/database.php';

		$this->assertFileExists($databaseFile);
		$this->lintFile($databaseFile);

		// The modern calls use the short alias, like the rest of the generated code
		$database = (string) file_get_contents($databaseFile);
		$this->assertStringContainsString('use SMF\Db\DatabaseApi as Db;', $database);
		$this->assertStringContainsString('Db::$db->create_table(', $database);
		$this->assertStringNotContainsString('\SMF\Db\DatabaseApi::$db', $database);

		$calls = $this->runDatabaseFile($databaseFile);

		$this->assertSame('create_table', $calls[0][0]);
		$this->assertSame('{db_prefix}demo_table', $calls[0][1]);
		$this->assertSame('query', $calls[1][0]);
		$this->assertStringContainsString('SELECT task', $calls[1][1]);
		$this->assertSame('insert', $calls[2][0]);
		$this->assertSame('{db_prefix}scheduled_tasks', $calls[2][1]);
		$this->assertSame(['cleanup', 'weekly_digest'], array_column($calls[2][3], 'task'));
	}

	#[Test]
	public function mixedTargetDatabaseSwitchesBranchAtRuntime(): void
	{
		require_once __DIR__ . '/Smf3Stubs.php';

		$base    = $this->makeTempDir();
		$builder = $this->makeBuilder($base, [
			'smf_target_version' => '2.1/3.0',
			'tables'             => $this->tables(),
			'scheduled_tasks'    => $this->scheduledTasks(),
		]);

		$builder->create('<?php');
		package_flush_cache();

		$databaseFile = $base . '/demo/database.php';
		$database     = (string) file_get_contents($databaseFile);

		// Both branches are in place, and the modern one uses the short alias
		$this->assertStringContainsString("version_compare(SMF_VERSION, '3.0', '>=')", $database);
		$this->assertStringContainsString('use SMF\Db\DatabaseApi as Db;', $database);
		$this->assertStringContainsString('Db::$db->create_table(', $database);
		$this->assertStringContainsString("\$smcFunc['db_create_table']", $database);
		$this->assertStringContainsString("\$smcFunc['db_insert']", $database);
		$this->assertStringNotContainsString('\SMF\Db\DatabaseApi::$db', $database);

		// Exactly one check per installer block: the table loop, the check of
		// the already registered tasks and the insert of the missing ones
		$this->assertSame(3, substr_count($database, "version_compare(SMF_VERSION, '3.0', '>=')"));
		$this->assertStringContainsString('$taskRows = [', $database);
		$this->lintFile($databaseFile);

		// The branch of the running version is executed
		$calls = $this->runDatabaseFile($databaseFile);

		$this->assertSame('create_table', $calls[0][0]);
		$this->assertSame('{db_prefix}demo_table', $calls[0][1]);
		$this->assertSame('query', $calls[1][0]);
		$this->assertStringContainsString('SELECT task', $calls[1][1]);
		$this->assertSame('insert', $calls[2][0]);
		$this->assertSame('{db_prefix}scheduled_tasks', $calls[2][1]);
		$this->assertSame(['cleanup', 'weekly_digest'], array_column($calls[2][3], 'task'));
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function tables(): array
	{
		return [
			[
				'name'    => 'demo_table',
				'columns' => [
					['name' => 'id', 'type' => 'int', 'null' => false, 'size' => 10, 'auto' => true, 'default' => ''],
					['name' => 'title', 'type' => 'varchar', 'null' => false, 'size' => 255, 'auto' => false, 'default' => ''],
				],
			],
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function scheduledTasks(): array
	{
		return [
			0 => [
				'slug'         => 'cleanup',
				'names'        => [],
				'descriptions' => [],
				'regularity'   => 0,
				'callable'     => '\\Bugo\\Demo\\Tasks\\Cleanup::execute',
			],
			1 => [
				'slug'         => 'weekly_digest',
				'names'        => [],
				'descriptions' => [],
				'regularity'   => 1,
				'callable'     => '\\Bugo\\Demo\\Tasks\\WeeklyDigest::execute',
			],
		];
	}

	/**
	 * Runs the generated installer against a recording stub of the SMF 3.0
	 * database API.
	 *
	 * @return list<array>
	 */
	private function runDatabaseFile(string $databaseFile): array
	{
		$calls = [];

		DatabaseApi::$db = new class ($calls) {
			public function __construct(private array &$calls)
			{
			}

			public function query(string $db_string, array $db_values = []): object
			{
				$this->calls[] = ['query', $db_string, $db_values];

				return new stdClass();
			}

			public function fetch_assoc(object $result): array|false
			{
				return false;
			}

			public function free_result(object $result): bool
			{
				return true;
			}

			public function create_table(string $table, array $columns, array $indexes = []): void
			{
				$this->calls[] = ['create_table', $table, $columns, $indexes];
			}

			public function insert(string $method, string $table, array $columns, array $data, array $keys, int $returnmode = 0): void
			{
				$this->calls[] = ['insert', $table, $columns, $data, $keys, $returnmode];
			}
		};

		// The installer collects the tables into its own, initially empty list
		$tables = [];

		include $databaseFile;

		return $calls;
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
			'languages'          => ['english'],
			'title'              => ['english' => 'Demo'],
			'description'        => ['english' => 'Desc'],
		], $overrides);
	}

	private function makeTempDir(): string
	{
		$dir = sys_get_temp_dir() . '/smm_versions_' . bin2hex(random_bytes(4));

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
