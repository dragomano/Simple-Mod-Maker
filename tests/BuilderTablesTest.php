<?php declare(strict_types=1);

namespace Tests;

use Bugo\SimpleModMaker\Builder;
use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * The generated database.php is a real installer script, so the schema it
 * passes to db_create_table() is verified by including the file with a stub
 * $smcFunc instead of matching the formatted source.
 */
class BuilderTablesTest extends TestCase
{
	/** @var list<string> */
	private array $tempDirs = [];

	public function testTableWithoutAutoColumnGetsAnEmptyIndexesArray(): void
	{
		$calls = $this->createAndRunDatabase([
			[
				'name'    => 'demo_table',
				'columns' => [
					['name' => 'title', 'type' => 'varchar', 'null' => false, 'size' => 255, 'auto' => false, 'default' => ''],
					['name' => 'notes', 'type' => 'mediumtext', 'null' => true, 'size' => 0, 'auto' => false, 'default' => ''],
				],
			],
		]);

		$this->assertCount(1, $calls);
		$this->assertSame('{db_prefix}demo_table', $calls[0][0]);
		$this->assertSame(['title', 'notes'], array_column($calls[0][1], 'name'));
		$this->assertSame([], $calls[0][2]);
	}

	public function testAutoIncrementColumnBecomesThePrimaryIndex(): void
	{
		$calls = $this->createAndRunDatabase([
			[
				'name'    => 'demo_table',
				'columns' => [
					['name' => 'id', 'type' => 'int', 'null' => false, 'size' => 10, 'auto' => true, 'default' => ''],
					['name' => 'title', 'type' => 'varchar', 'null' => false, 'size' => 255, 'auto' => false, 'default' => ''],
				],
			],
		]);

		$this->assertCount(1, $calls);
		$this->assertSame([['type' => 'primary', 'columns' => ['id']]], $calls[0][2]);
		$this->assertTrue($calls[0][1][0]['auto']);
	}

	/**
	 * @param list<array{name: string, columns: list<array<string, mixed>>}> $schema
	 * @return list<array{0: string, 1: array, 2: array}>
	 */
	private function createAndRunDatabase(array $schema): array
	{
		$base = sys_get_temp_dir() . '/smm_tables_' . bin2hex(random_bytes(4));

		mkdir($base, 0777, true);

		$this->tempDirs[] = $base;

		$builder = new Builder([
			'skeleton'  => $this->skeleton(['tables' => $schema]),
			'classname' => 'Demo',
			'snakename' => 'demo',
			'path'      => $base . '/demo',
		]);

		$builder->create('<?php');
		package_flush_cache();

		$databaseFile = $base . '/demo/database.php';

		$this->assertFileExists($databaseFile);

		$calls   = [];
		$smcFunc = [
			'db_create_table' => function (string $name, array $columns, array $indexes) use (&$calls): void {
				$calls[] = [$name, $columns, $indexes];
			},
		];

		// The installer collects the tables into its own, initially empty list
		$tables = [];

		include $databaseFile;

		return $calls;
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
