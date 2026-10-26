<?php declare(strict_types=1);

namespace Tests;

use ParseError;
use stdClass;
use Bugo\SimpleModMaker\Builder;
use Bugo\SimpleModMaker\Generator;
use Bugo\SimpleModMaker\Hook;
use Bugo\SimpleModMaker\Hooks\HookFactory;
use FilesystemIterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionMethod;
use ZipArchive;

/**
 * The integration checks of the whole generated package: the matrix of
 * settings areas, source layouts and SMF targets is built the way the
 * handler does it, and every package must pass the syntax check of all its
 * PHP files, the consistency between the manifest and the archive, and the
 * execution of its key handlers on the SMF stubs.
 */
#[CoversClass(Generator::class)]
#[CoversClass(Builder::class)]
class PackageIntegrationTest extends TestCase
{
	/** @var list<string> */
	private array $tempDirs = [];

	private int $combos = 0;

	protected function tearDown(): void
	{
		foreach ($this->tempDirs as $dir) {
			self::deleteDirectory($dir);
		}

		$this->tempDirs = [];
		$this->combos   = 0;

		unset(
			$GLOBALS['modSettings'],
			$_GET['save'],
			$_REQUEST['sa']
		);
	}

	#[Test]
	public function wholePackagesFromMatrixPassAllChecks(): void
	{
		foreach ([0, 1, 2, 3] as $settingsArea) {
			foreach ([false, true] as $makeDir) {
				foreach (['2.1', '3.0', '2.1/3.0'] as $targetVersion) {
					$this->checkWholePackage($settingsArea, $makeDir, $targetVersion);
				}
			}
		}
	}

	/**
	 * The matrix above builds packages out of the hooks of the settings areas.
	 * This check takes every hook the factory knows: each of them must produce a
	 * method of the generated class and a registration in it, and every body it
	 * renders must be valid PHP, either alone or behind a version check.
	 */
	#[Test]
	public function everyAvailableHookIsGeneratedAndRegistered(): void
	{
		$hooks = HookFactory::getAvailableHooks();

		$this->assertNotEmpty($hooks);

		foreach (['2.1', '3.0', '2.1/3.0'] as $targetVersion) {
			$context  = 'target=' . $targetVersion;
			$skeleton = array_merge($this->matrixSkeleton(0, false, $targetVersion), ['hooks' => $hooks]);

			$package = $this->buildPackage($skeleton, 'Demo', 'demo');

			$this->lintAllPhpFiles($package['path']);

			$this->runHandlersOnStubs(
				$package['path'] . '/Sources/' . $skeleton['filename'] . '.php',
				$skeleton['namespace'] . '\Demo',
				$skeleton,
				$context,
				$this->factoryHookMethodNames()
			);
		}
	}

	#[Test]
	public function fullPackageManifestMatchesArchiveContents(): void
	{
		$expectedFor = [
			'2.1'     => '2.1.*',
			'3.0'     => '3.0.*',
			'2.1/3.0' => '2.1.*, 3.0.*',
		];

		foreach ($expectedFor as $targetVersion => $for) {
			$skeleton = array_merge($this->matrixSkeleton(2, true, $targetVersion), [
				'use_lang_dir'    => true,
				'make_script'     => true,
				'make_css'        => true,
				'make_readme'     => true,
				'readmes'         => ['english' => 'Readme {mod_name}', 'russian' => 'Readme {mod_name}'],
				'description'     => ['english' => 'Desc', 'russian' => 'Desc ru'],
				'min_php_version' => '8.2',
			]);

			$package = $this->buildPackage($skeleton, 'Demo', 'demo');

			$this->lintAllPhpFiles($package['path']);

			$xml = simplexml_load_file($package['path'] . '/package-info.xml');

			$this->assertNotFalse($xml);

			$namespace = 'http://www.simplemachines.org/xml/package-info';

			$this->assertSame(
				$for,
				(string) ($xml->children($namespace)->install->attributes()['for'] ?? ''),
				$targetVersion
			);
			$this->assertSame(
				$for,
				(string) ($xml->children($namespace)->uninstall->attributes()['for'] ?? ''),
				$targetVersion
			);

			$this->createArchiveAndCheck($package);
		}
	}

	#[Test]
	public function scheduledTaskRegularitiesMapToDatabaseRows(): void
	{
		$skeleton = array_merge($this->matrixSkeleton(0, true, '2.1'), [
			'scheduled_tasks' => [
				0 => ['slug' => 'task_daily', 'names' => [], 'descriptions' => [], 'regularity' => 0],
				1 => ['slug' => 'task_weekly', 'names' => [], 'descriptions' => [], 'regularity' => 1],
				2 => ['slug' => 'task_monthly', 'names' => [], 'descriptions' => [], 'regularity' => 2],
			],
		]);

		$package = $this->buildPackage($skeleton, 'Demo', 'demo');

		$this->lintAllPhpFiles($package['path']);

		$calls = [];

		$smcFunc = [
			'db_create_table' => function (string $name, array $columns, array $indexes) use (&$calls): void {
				$calls[] = ['create_table', $name, $columns, $indexes];
			},
			'db_query' => function (string $identifier, string $sql, array $params) use (&$calls): object {
				$calls[] = ['query', $sql, $params];

				return new stdClass();
			},
			'db_fetch_assoc' => static fn (): bool => false,
			'db_free_result' => static fn (): bool => true,
			'db_insert' => function (string $method, string $table, array $columns, array $rows, array $keys) use (&$calls): void {
				$calls[] = ['insert', $table, $columns, $rows, $keys];
			},
		];

		// The installer collects the tables into its own, initially empty list
		$tables = [];

		include $package['path'] . '/database.php';

		$inserts = array_values(array_filter($calls, static fn (array $call): bool => $call[0] === 'insert'));

		$this->assertCount(1, $inserts);
		$this->assertSame('{db_prefix}scheduled_tasks', $inserts[0][1]);

		$rows = array_column($inserts[0][3], null, 'task');

		foreach ([
			'task_daily'   => [1, 'd'],
			'task_weekly'  => [1, 'w'],
			'task_monthly' => [4, 'w'],
		] as $slug => [$regularity, $unit]) {
			$class = str_replace(' ', '', ucwords(str_replace('_', ' ', $slug)));

			$this->assertArrayHasKey($slug, $rows, $slug);
			$this->assertSame($regularity, $rows[$slug]['time_regularity'], $slug);
			$this->assertSame($unit, $rows[$slug]['time_unit'], $slug);

			// The stored callable keeps the doubled separators of the class map
			$this->assertSame(
				str_replace('\\', '\\\\', '\\' . $skeleton['namespace'] . '\\Demo\\Tasks\\' . $class . '::execute'),
				$rows[$slug]['callable'],
				$slug
			);
		}
	}

	#[Test]
	public function hostileStringsSurviveTheFullPackageBuild(): void
	{
		$hostile = "O'Brien \"quoted\" & <b>tag</b> Привет 😀 trail\\";

		$skeleton = array_merge($this->matrixSkeleton(2, true, '2.1/3.0'), [
			'name'        => $hostile,
			'author'      => "O'Brien \\",
			'site'        => 'https://example.test/?q=" & <x>',
			'title'       => ['english' => $hostile],
			'description' => ['english' => "Back\\slash 'quoted' & done"],
			'readmes'     => ['english' => $hostile . ' {mod_name}'],
			'make_readme' => true,
		]);

		$package = $this->buildPackage($skeleton, 'Demo', 'demo');

		$this->lintAllPhpFiles($package['path']);
		$this->createArchiveAndCheck($package);

		$txt = [];

		include $package['path'] . '/Themes/default/languages/Demo.english.php';

		$this->assertSame($hostile, $txt['demo_title']);
		$this->assertSame("Back\\slash 'quoted' & done", $txt['demo_description']);
		$this->assertSame('Enable feature', $txt['demo_enable_feature']);
		$this->assertSame(['a', 'b'], $txt['demo_choice_set']);
	}

	private function checkWholePackage(int $settingsArea, bool $makeDir, string $targetVersion): void
	{
		$context  = 'settings_area=' . $settingsArea . ', make_dir=' . (int) $makeDir . ', target=' . $targetVersion;
		$skeleton = $this->matrixSkeleton($settingsArea, $makeDir, $targetVersion);
		$package  = $this->buildPackage($skeleton, 'Demo', 'demo');

		$this->lintAllPhpFiles($package['path']);
		$this->createArchiveAndCheck($package);

		$mainFile = empty($makeDir)
			? $package['path'] . '/Sources/' . $skeleton['filename'] . '.php'
			: $package['path'] . '/Sources/Demo/Integration.php';

		$fqcn = $skeleton['namespace'] . '\Demo' . (empty($makeDir) ? '' : '\Integration');

		$this->runHandlersOnStubs($mainFile, $fqcn, $skeleton, $context);
	}

	private function runHandlersOnStubs(string $mainFile, string $fqcn, array $skeleton, string $context, ?array $hookMethods = null): void
	{
		$params = [
			'main_file'     => $mainFile,
			'fqcn'          => $fqcn,
			'legacy'        => $skeleton['smf_target_version'] === '2.1',
			'settings_area' => (int) $skeleton['settings_area'],
			'make_dir'      => ! empty($skeleton['make_dir']),
			'hooks'         => $skeleton['hooks'],
			'hook_methods'  => array_intersect_key($hookMethods ?? $this->hookMethodNames(), array_flip($skeleton['hooks'])),
			'txt'           => $this->langStrings(),
			'sourcedir'     => $this->makeSourcedir(),
			'namespace'     => $skeleton['namespace'],
			'snake_name'    => 'demo',
		];

		$report = $this->runHandlerProcess($params, $context);

		// Every registered hook carries the expected callable of a generated method
		$this->assertCount(count($skeleton['hooks']), $report['registrations'], $context);

		foreach ($skeleton['hooks'] as $hook) {
			$this->assertSame(
				$fqcn . '::' . $params['hook_methods'][$hook] . '#',
				$report['registrations'][$hook] ?? null,
				$context . ', ' . $hook
			);

			$this->assertTrue($report['methods'][$hook], $context . ', ' . $hook);
		}

		match ($params['settings_area']) {
			1       => $this->assertArea1Handler($report, $skeleton, $context),
			2       => $this->assertArea2Handlers($report, $skeleton, $fqcn, $context),
			3       => $this->assertArea3Handlers($report, $skeleton, $fqcn, $context),
			default => null,
		};

		if ($params['make_dir']) {
			$this->assertSame(
				[$skeleton['namespace'] . '\\Demo\\' => 'Demo/'],
				$report['class_map'] ?? null,
				$context
			);
		}
	}

	/**
	 * Executes the handlers in a separate PHP process, where the recording
	 * stubs cannot collide with the real SMF helpers of this process.
	 *
	 * @param array<string, mixed> $params
	 * @return array<string, mixed>
	 */
	private function runHandlerProcess(array $params, string $context): array
	{
		$paramsFile = tempnam(sys_get_temp_dir(), 'smm_params_');
		$scriptFile = tempnam(sys_get_temp_dir(), 'smm_runner_');

		file_put_contents($paramsFile, json_encode($params));
		file_put_contents($scriptFile, "<?php declare(strict_types=1);\n\nrequire " . var_export(__DIR__ . '/HandlerRunner.php', true) . ";\n\nHandlerRunner::run(json_decode((string) file_get_contents(" . var_export($paramsFile, true) . "), true));\n");

		try {
			exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($scriptFile) . ' 2>&1', $output, $exitCode);

			$this->assertSame(0, $exitCode, $context . "\n" . implode("\n", $output));

			$report = json_decode((string) end($output), true);

			$this->assertIsArray($report, $context);

			if (($report['fatal'] ?? null) !== null) {
				$this->fail($context . "\n" . $report['fatal']);
			}

			return $report;
		} finally {
			@unlink($paramsFile);
			@unlink($scriptFile);
		}
	}

	/**
	 * The generated method of every hook the factory provides, named the way
	 * the generator names it.
	 *
	 * @return array<string, string>
	 */
	private function factoryHookMethodNames(): array
	{
		$methods = [];

		foreach (HookFactory::getAvailableHooks() as $hook) {
			$methods[$hook] = Hook::toMethodName($hook);
		}

		return $methods;
	}

	private function hookMethodNames(): array
	{
		return [
			Hook::ADMIN_AREAS          => 'adminAreas',
			Hook::ADMIN_SEARCH         => 'adminSearch',
			Hook::AUTOLOAD             => 'autoload',
			Hook::CREDITS              => 'credits',
			Hook::GENERAL_MOD_SETTINGS => 'generalModSettings',
			Hook::LOAD_THEME           => 'loadTheme',
			Hook::MODIFY_MODIFICATIONS => 'modifyModifications',
			Hook::PRE_LOG_STATS        => 'preLogStats',
			Hook::WEEKLY_MAINTENANCE   => 'weeklyMaintenance',
		];
	}

	private function assertArea1Handler(array $report, array $skeleton, string $context): void
	{
		$configVars = $report['area1']['config_vars'];

		$this->assertCount(4, $configVars, $context);
		$this->assertSame(['title', 'smm_existing'], $configVars[0], $context);
		$this->assertSame(['check', 'demo_enable_feature'], $configVars[1], $context);
		$this->assertSame(['select', 'demo_choice', ['a', 'b'], 'multiple' => false], $configVars[2], $context);
		$this->assertSame(['callback', 'demo_custom_block'], $configVars[3], $context);

		$this->assertDefaultsApplied($report, ['demo_enable_feature' => true, 'demo_choice' => 'a'], $skeleton, $context);
		$this->assertTemplateLoaded($report, $context);
	}

	private function assertArea2Handlers(array $report, array $skeleton, string $fqcn, string $context): void
	{
		$configVars = [
			['check', 'demo_enable_feature'],
			['select', 'demo_choice', ['a', 'b'], 'multiple' => false],
			['callback', 'demo_custom_block'],
		];

		$this->assertSame($configVars, $report['area2']['config_vars'], $context);

		if ($skeleton['smf_target_version'] === '2.1') {
			$this->assertSame([$configVars], $report['save_db_settings'] ?? [], $context);
			$this->assertSame([$configVars], $report['prepare_db_setting_context'] ?? [], $context);
			$this->assertSame(
				['action=admin;area=modsettings;sa=demo'],
				$report['redirectexit'] ?? [],
				$context
			);
		} else {
			$this->assertSame(
				[['saveDBSettings', $configVars], ['prepareDBSettingContext', $configVars]],
				$report['modern']['acp'],
				$context
			);
			$this->assertSame([['redirectexit', 'action=admin;area=modsettings;sa=demo']], $report['modern']['utils'], $context);
		}

		$this->assertTemplateLoaded($report, $context);

		$adminAreas = $report['area2']['admin_areas'];

		$this->assertSame(
			['Demo Mod'],
			$adminAreas['config']['areas']['modsettings']['subsections']['demo'],
			$context
		);

		$this->assertSame([$fqcn, 'settings'], $report['area2']['sub_actions']['demo'], $context);

		$adminSearch = $report['area2']['admin_search'];

		$this->assertSame(['Demo'], $adminSearch[0], $context);
		$this->assertSame([], $adminSearch[1], $context);
		$this->assertSame([[[$fqcn, 'settings'], 'area=modsettings;sa=demo']], $adminSearch[2], $context);
	}

	private function assertArea3Handlers(array $report, array $skeleton, string $fqcn, string $context): void
	{
		$configVars = [
			['check', 'demo_enable_feature'],
			['select', 'demo_choice', ['a', 'b'], 'multiple' => false],
			['callback', 'demo_custom_block'],
		];

		// The settings() dispatch reaches sectionExample1 through the SMF helpers
		if ($skeleton['smf_target_version'] === '2.1') {
			$this->assertSame([$configVars], $report['prepare_db_setting_context'] ?? [], $context);
		} else {
			$this->assertSame([['prepareDBSettingContext', $configVars]], $report['modern']['acp'], $context);
		}

		$this->assertSame($configVars, $report['area3']['section1_return'], $context);
		$this->assertTemplateLoaded($report, $context);

		$area = $report['area3']['admin_areas']['config']['areas']['demo'];

		$this->assertSame('Demo Mod', $area['label'], $context);
		$this->assertSame([$fqcn, 'settings'], $area['function'], $context);
		$this->assertSame(['Section 1'], $area['subsections']['section1'], $context);
	}

	/**
	 * @param array<string, mixed> $report
	 * @param array<string, bool|int|string> $defaults
	 */
	private function assertDefaultsApplied(array $report, array $defaults, array $skeleton, string $context): void
	{
		if ($skeleton['smf_target_version'] === '2.1') {
			$updates = $report['update_settings'] ?? [];

			$this->assertNotEmpty($updates, $context);

			foreach ($updates as $update) {
				$this->assertSame($defaults, $update, $context);
			}

			return;
		}

		$updates = array_filter(
			$report['modern']['config'],
			static fn (array $call): bool => $call[0] === 'updateModSettings'
		);

		$this->assertNotEmpty($updates, $context);

		foreach ($updates as $update) {
			$this->assertSame(['updateModSettings', $defaults], $update, $context);
		}
	}

	private function assertTemplateLoaded(array $report, string $context): void
	{
		$this->assertSame(
			[['loadTemplate', 'Demo']],
			array_unique($report['theme'] ?? [], SORT_REGULAR),
			$context
		);
	}

	/**
	 * The strings of the generated language file, as the installed mod sees them
	 */
	private function langStrings(): array
	{
		return [
			'demo_title'          => 'Demo Mod',
			'demo_description'    => 'Desc',
			'demo_enable_feature' => 'Enable feature',
			'demo_choice'         => 'Choice',
			'demo_choice_set'     => ['a', 'b'],
			'demo_custom_block'   => 'Custom block',
			'demo_section1_title' => 'Section 1',
			'demo_section2_title' => 'Section 2',
		];
	}

	private function matrixSkeleton(int $settingsArea, bool $makeDir, string $targetVersion): array
	{
		$this->combos++;

		$hooks = match ($settingsArea) {
			1       => [Hook::GENERAL_MOD_SETTINGS],
			2       => [Hook::ADMIN_AREAS, Hook::ADMIN_SEARCH, Hook::MODIFY_MODIFICATIONS],
			3       => [Hook::ADMIN_AREAS],
			default => [Hook::LOAD_THEME, Hook::PRE_LOG_STATS],
		};

		// The task registrations imply the autoload and the maintenance hook
		if ($makeDir) {
			$hooks[] = Hook::AUTOLOAD;
			$hooks[] = Hook::WEEKLY_MAINTENANCE;
		}

		$hooks[] = Hook::CREDITS;

		return [
			'name'               => 'Demo Mod',
			'filename'           => 'Class-Demo',
			'hooks'              => $hooks,
			'author'             => 'Bugo',
			'namespace'          => 'Bugo\\M' . $this->combos,
			'email'              => 'bugo@example.test',
			'readmes'            => [],
			'version'            => '1.0',
			'site'               => 'https://example.test',
			'settings_area'      => $settingsArea,
			'license'            => 'mit',
			'license_data'       => ['link' => 'https://x', 'full_name' => 'MIT'],
			'make_dir'           => $makeDir,
			'use_lang_dir'       => false,
			'make_template'      => false,
			'make_script'        => false,
			'make_css'           => false,
			'make_readme'        => false,
			'add_copyrights'     => true,
			'min_php_version'    => '',
			'smf_target_version' => $targetVersion,
			'callbacks'          => [],
			'options'            => [
				0 => [
					'name'         => 'enable_feature',
					'type'         => 'check',
					'default'      => true,
					'variants'     => '',
					'translations' => ['english' => 'Enable feature'],
				],
				1 => [
					'name'         => 'choice',
					'type'         => 'select',
					'default'      => 'a',
					'variants'     => 'a|b',
					'translations' => ['english' => 'Choice'],
				],
				2 => [
					'name'         => 'custom_block',
					'type'         => 'callback',
					'default'      => '',
					'variants'     => '',
					'translations' => ['english' => 'Custom block'],
				],
			],
			'tables'             => [
				0 => [
					'name'    => 'demo_table',
					'columns' => [
						['name' => 'id', 'type' => 'int', 'null' => false, 'size' => 10, 'auto' => true, 'default' => ''],
						['name' => 'title', 'type' => 'varchar', 'null' => false, 'size' => 255, 'auto' => false, 'default' => ''],
					],
				],
			],
			'scheduled_tasks'    => $makeDir ? [
				0 => ['slug' => 'daily_cleanup', 'names' => [], 'descriptions' => [], 'regularity' => 0],
			] : [],
			'background_tasks'   => $makeDir ? [
				0 => ['classname' => 'CleanupBackground', 'regularity' => ''],
			] : [],
			'legacy_tasks'       => $makeDir ? [
				0 => ['method' => 'runWeekly', 'regularity' => 1],
			] : [],
			'languages'          => ['english'],
			'title'              => ['english' => 'Demo'],
			'description'        => ['english' => 'Desc'],
		];
	}

	/**
	 * Builds the package the way the handler does: generation, files, manifest
	 *
	 * @return array{path: string, builder: Builder}
	 */
	private function buildPackage(array $skeleton, string $classname, string $snakeName): array
	{
		$base = $this->makeTempDir();
		$path = $base . '/' . $snakeName . '_' . $skeleton['version'];

		$result = (new Generator())->generate($skeleton, $classname, $snakeName);

		$GLOBALS['modSettings'] = ['package_disable_cache' => 1];

		$builder = new Builder([
			'skeleton'  => $skeleton,
			'classname' => $classname,
			'snakename' => $snakeName,
			'path'      => $path,
		]);

		$builder->create($result['content']);

		if ($result['tasks'] !== []) {
			$builder->createTasks($result['tasks']);
		}

		package_flush_cache();

		(new ReflectionMethod($builder, 'preparePackageInfo'))->invoke($builder);

		package_flush_cache();

		return ['path' => $path, 'builder' => $builder];
	}

	private function createArchiveAndCheck(array $package): void
	{
		$archive = (new ReflectionMethod($package['builder'], 'createArchive'))->invoke($package['builder']);

		$this->assertFileExists($archive);
		$this->assertGreaterThan(0, filesize($archive));

		$zip = new ZipArchive();

		$this->assertTrue($zip->open($archive));

		$entries = [];

		for ($i = 0; $i < $zip->numFiles; $i++) {
			$entries[] = $this->normalizePath((string) $zip->getNameIndex($i));
		}

		$zip->close();

		sort($entries);

		$this->assertManifestMatchesArchive($package['path'] . '/package-info.xml', $entries);

		(new ReflectionMethod($package['builder'], 'removeArchive'))->invoke($package['builder']);

		$this->assertFileDoesNotExist($archive);
	}

	/**
	 * Every path the manifest copies from the package must be present in the
	 * built archive: file entries exactly, directory entries by prefix.
	 *
	 * @param list<string> $entries
	 */
	private function assertManifestMatchesArchive(string $manifestFile, array $entries): void
	{
		$xml = simplexml_load_file($manifestFile);

		$this->assertNotFalse($xml, 'The generated manifest must be well-formed XML.');

		$namespace = 'http://www.simplemachines.org/xml/package-info';

		$files = ['package-info.xml', 'license.txt'];
		$dirs  = [];

		foreach (['install', 'uninstall'] as $action) {
			foreach ($xml->children($namespace)->{$action}->children($namespace) as $child) {
				$name = $child->getName();

				if (in_array($name, ['code', 'database', 'readme'], true)) {
					$files[] = $this->normalizePath((string) $child);
				} elseif ($name === 'require-file') {
					$files[] = $this->normalizePath((string) ($child->attributes()['name'] ?? ''));
				} elseif ($name === 'require-dir') {
					$dirs[] = $this->normalizePath((string) ($child->attributes()['name'] ?? ''));
				}
			}
		}

		foreach ($files as $file) {
			$this->assertContains($file, $entries, 'The manifest copies a missing file: ' . $file);
		}

		foreach ($dirs as $dir) {
			$inside = array_filter(
				$entries,
				static fn (string $entry): bool => str_starts_with($entry, $dir . '/')
			);

			$this->assertNotEmpty($inside, 'The manifest copies a missing directory: ' . $dir);
		}
	}

	private function normalizePath(string $path): string
	{
		return str_replace('\\', '/', $path);
	}

	private function lintAllPhpFiles(string $dir): void
	{
		$files = [];

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
		);

		foreach ($iterator as $file) {
			if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
				$files[] = $file->getPathname();
			}
		}

		sort($files);

		$this->assertNotEmpty($files, 'The package must contain PHP files.');

		foreach ($files as $file) {
			try {
				token_get_all((string) file_get_contents($file), TOKEN_PARSE);
			} catch (ParseError $e) {
				$this->fail($e->getMessage() . "\n---\n" . $file);
			}
		}
	}

	private function makeSourcedir(): string
	{
		$dir = $this->makeTempDir();

		file_put_contents($dir . '/ManageSettings.php', "<?php\n");
		file_put_contents($dir . '/ManageServer.php', "<?php\n");

		return $dir;
	}

	private function makeTempDir(): string
	{
		$dir = sys_get_temp_dir() . '/smm_package_' . bin2hex(random_bytes(4));

		mkdir($dir, 0777, true);

		$this->tempDirs[] = $dir;

		return $dir;
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
