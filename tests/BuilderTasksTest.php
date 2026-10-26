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
 * The task registration must be repeatable and reversible: the installer
 * skips the rows that are already in the database, and the uninstall scripts
 * clean the registrations on every uninstall, while the user data (settings
 * and tables) is removed only on request.
 */
#[CoversClass(Builder::class)]
class BuilderTasksTest extends TestCase
{
	/** @var list<string> */
	private array $tempDirs = [];

	#[Test]
	public function freshInstallationInsertsAllTaskRows(): void
	{
		$calls = $this->runDatabaseFile($this->makeDatabaseFile(), []);

		$this->assertCount(2, $calls);

		// The existence check covers all the rows of this installation
		$this->assertSame(['cleanup', 'weekly_digest'], $calls[0][2]['tasks']);

		$this->assertSame('insert', $calls[1][0]);
		$this->assertSame('{db_prefix}scheduled_tasks', $calls[1][1]);
		$this->assertSame(['cleanup', 'weekly_digest'], array_column($calls[1][3], 'task'));
		$this->assertSame('\\Bugo\\Demo\\Tasks\\Cleanup::execute', $calls[1][3][0]['callable']);
		$this->assertSame(['id_task'], $calls[1][4]);
	}

	#[Test]
	public function repeatedInstallationSkipsExistingTaskRows(): void
	{
		$calls = $this->runDatabaseFile($this->makeDatabaseFile(), [['task' => 'cleanup']]);

		$this->assertCount(2, $calls);

		// The registered task keeps its row, the missing one is inserted
		$this->assertSame('insert', $calls[1][0]);
		$this->assertSame(['weekly_digest'], array_column($calls[1][3], 'task'));
	}

	#[Test]
	public function uninstallScriptRemovesTaskRegistrations(): void
	{
		$file = $this->makeUninstallScript();

		$calls = [];

		$smcFunc = [
			'db_query' => function (string $identifier, string $sql, array $params) use (&$calls): object {
				$calls[] = ['query', $sql, $params];

				return new stdClass();
			},
		];

		include $file;

		$this->assertCount(2, $calls);

		$this->assertStringContainsString('DELETE FROM {db_prefix}scheduled_tasks', $calls[0][1]);
		$this->assertSame(['cleanup', 'weekly_digest'], $calls[0][2]['tasks']);

		$this->assertStringContainsString('DELETE FROM {db_prefix}background_tasks', $calls[1][1]);
		$this->assertSame(
			['$sourcedir/Demo/Tasks/CleanupBackground.php'],
			$calls[1][2]['files']
		);
	}

	#[Test]
	public function uninstallDatabaseScriptRemovesSettingsAndTables(): void
	{
		$file = $this->makeUninstallDatabaseScript();

		$calls = [];

		$smcFunc = [
			'db_query' => function (string $identifier, string $sql, array $params) use (&$calls): object {
				$calls[] = ['query', $sql, $params];

				return new stdClass();
			},
			'db_drop_table' => function (string $table) use (&$calls): void {
				$calls[] = ['drop_table', $table];
			},
		];

		include $file;

		$this->assertCount(2, $calls);

		$this->assertSame('query', $calls[0][0]);
		$this->assertStringContainsString('DELETE FROM {db_prefix}settings', $calls[0][1]);
		$this->assertSame(['demo_enable_thing'], $calls[0][2]['settings']);

		$this->assertSame(['drop_table', '{db_prefix}demo_table'], $calls[1]);
	}

	#[Test]
	public function uninstallScriptsAreRegisteredInPackageInfo(): void
	{
		$base    = $this->makeTempDir();
		$builder = $this->makeBuilder($base, $this->fullSkeleton());

		$builder->create('<?php');
		package_flush_cache();

		$method = new ReflectionMethod($builder, 'preparePackageInfo');
		$method->invoke($builder);
		package_flush_cache();

		$manifest = (string) file_get_contents($base . '/demo/package-info.xml');
		$uninstall = substr($manifest, (int) strpos($manifest, '<uninstall'));

		$this->assertFileExists($base . '/demo/uninstall.php');
		$this->assertFileExists($base . '/demo/uninstall-database.php');

		// The scripts run before the hook is removed and the files are deleted
		$this->assertStringContainsString('<code>uninstall.php</code>', $uninstall);
		$this->assertStringContainsString('<database>uninstall-database.php</database>', $uninstall);
		$this->assertLessThan(strpos($uninstall, '<hook'), strpos($uninstall, '<code>'));

		$this->lintFile($base . '/demo/uninstall.php');
		$this->lintFile($base . '/demo/uninstall-database.php');
	}

	#[Test]
	public function modWithoutTasksAndDataGetsNoUninstallScripts(): void
	{
		$base    = $this->makeTempDir();
		$builder = $this->makeBuilder($base, $this->skeleton());

		$builder->create('<?php');
		package_flush_cache();

		$this->assertFileDoesNotExist($base . '/demo/uninstall.php');
		$this->assertFileDoesNotExist($base . '/demo/uninstall-database.php');

		$method = new ReflectionMethod($builder, 'preparePackageInfo');
		$method->invoke($builder);
		package_flush_cache();

		$manifest  = (string) file_get_contents($base . '/demo/package-info.xml');
		$uninstall = substr($manifest, (int) strpos($manifest, '<uninstall'));

		$this->assertStringNotContainsString('<code>', $uninstall);
		$this->assertStringNotContainsString('<database>', $uninstall);
	}

	#[Test]
	public function tasksOnlyModGetsNoUninstallDatabaseScript(): void
	{
		$base    = $this->makeTempDir();
		$builder = $this->makeBuilder($base, $this->skeleton([
			'scheduled_tasks'  => $this->scheduledTasks(),
			'background_tasks' => $this->backgroundTasks(),
		]));

		$builder->create('<?php');
		package_flush_cache();

		$this->assertFileExists($base . '/demo/uninstall.php');
		$this->assertFileDoesNotExist($base . '/demo/uninstall-database.php');

		$method = new ReflectionMethod($builder, 'preparePackageInfo');
		$method->invoke($builder);
		package_flush_cache();

		$manifest = (string) file_get_contents($base . '/demo/package-info.xml');

		$this->assertStringContainsString('<code>uninstall.php</code>', $manifest);
		$this->assertStringNotContainsString('uninstall-database.php', $manifest);
	}

	#[Test]
	public function mixedUninstallScriptsSwitchBranchAtRuntime(): void
	{
		require_once __DIR__ . '/Smf3Stubs.php';

		$base    = $this->makeTempDir();
		$builder = $this->makeBuilder($base, array_merge(
			$this->fullSkeleton(),
			['smf_target_version' => '2.1/3.0']
		));

		$builder->create('<?php');
		package_flush_cache();

		foreach (['uninstall.php', 'uninstall-database.php'] as $file) {
			$script = (string) file_get_contents($base . '/demo/' . $file);

			$this->assertStringContainsString("version_compare(SMF_VERSION, '3.0', '>=')", $script);
			$this->assertStringContainsString('Db::$db->query(', $script);
			$this->assertStringContainsString("\$smcFunc['db_query']", $script);
			$this->lintFile($base . '/demo/' . $file);
		}

		// The branch of the running version is executed
		$calls = [];

		$smcFunc = [
			'db_query' => function (string $identifier, string $sql, array $params) use (&$calls): object {
				$calls[] = ['query', $sql, $params];

				return new stdClass();
			},
		];

		DatabaseApi::$db = new class ($calls) {
			public function __construct(private array &$calls)
			{
			}

			public function query(string $db_string, array $db_values = []): object
			{
				$this->calls[] = ['modern query', $db_string, $db_values];

				return new stdClass();
			}
		};

		include $base . '/demo/uninstall.php';

		$this->assertSame('modern query', $calls[0][0]);
		$this->assertStringContainsString('DELETE FROM {db_prefix}scheduled_tasks', $calls[0][1]);
	}

	/**
	 * Includes the generated installer with the given scheduled tasks already
	 * present in the database, so the idempotency of the registration is
	 * verified against a recording stub instead of the formatted source.
	 *
	 * @param list<array{task: string}> $existingTasks
	 * @return list<array>
	 */
	private function runDatabaseFile(string $databaseFile, array $existingTasks): array
	{
		$calls = [];
		$queue = $existingTasks;

		$smcFunc = [
			'db_query' => function (string $identifier, string $sql, array $params) use (&$calls): object {
				$calls[] = ['query', $sql, $params];

				return new stdClass();
			},
			'db_fetch_assoc' => static function (object $result) use (&$queue): array|false {
				return $queue !== [] ? array_shift($queue) : false;
			},
			'db_free_result' => static fn(object $result): bool => true,
			'db_insert' => function (string $method, string $table, array $columns, array $data, array $keys) use (&$calls): void {
				$calls[] = ['insert', $table, $columns, $data, $keys];
			},
		];

		include $databaseFile;

		return $calls;
	}

	private function makeDatabaseFile(): string
	{
		$base    = $this->makeTempDir();
		$builder = $this->makeBuilder($base, $this->skeleton([
			'scheduled_tasks' => $this->scheduledTasks(),
		]));

		$builder->create('<?php');
		package_flush_cache();

		$file = $base . '/demo/database.php';

		$this->assertFileExists($file);
		$this->lintFile($file);

		return $file;
	}

	private function makeUninstallScript(): string
	{
		$base    = $this->makeTempDir();
		$builder = $this->makeBuilder($base, $this->skeleton([
			'scheduled_tasks'  => $this->scheduledTasks(),
			'background_tasks' => $this->backgroundTasks(),
		]));

		$builder->create('<?php');
		package_flush_cache();

		$file = $base . '/demo/uninstall.php';

		$this->assertFileExists($file);
		$this->lintFile($file);

		return $file;
	}

	private function makeUninstallDatabaseScript(): string
	{
		$base    = $this->makeTempDir();
		$builder = $this->makeBuilder($base, $this->skeleton([
			'options' => [
				0 => [
					'name'         => 'enable_thing',
					'type'         => 'check',
					'default'      => '',
					'variants'     => '',
					'translations' => [],
				],
			],
			'tables'  => $this->tables(),
		]));

		$builder->create('<?php');
		package_flush_cache();

		$file = $base . '/demo/uninstall-database.php';

		$this->assertFileExists($file);
		$this->lintFile($file);

		return $file;
	}

	/**
	 * @param array<string, mixed> $overrides
	 */
	private function fullSkeleton(): array
	{
		return $this->skeleton([
			'options'          => [
				0 => [
					'name'         => 'enable_thing',
					'type'         => 'check',
					'default'      => '',
					'variants'     => '',
					'translations' => [],
				],
			],
			'tables'           => $this->tables(),
			'scheduled_tasks'  => $this->scheduledTasks(),
			'background_tasks' => $this->backgroundTasks(),
		]);
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
	 * @return array<string, mixed>
	 */
	private function backgroundTasks(): array
	{
		return [
			0 => [
				'classname'  => 'CleanupBackground',
				'regularity' => '',
			],
		];
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
			'readmes'            => [],
			'version'            => '1.0',
			'site'               => 'https://example.test',
			'settings_area'      => 0,
			'license'            => 'mit',
			'license_data'       => ['link' => 'https://x', 'full_name' => 'MIT'],
			'make_dir'           => true,
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
		$dir = sys_get_temp_dir() . '/smm_tasks_' . bin2hex(random_bytes(4));

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

	protected function tearDown(): void
	{
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
