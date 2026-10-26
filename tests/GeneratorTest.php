<?php declare(strict_types=1);

namespace Tests;

use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use FilesystemIterator;
use Bugo\SimpleModMaker\Builder;
use Bugo\SimpleModMaker\Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

#[CoversClass(Generator::class)]
class GeneratorTest extends TestCase
{
	private function skeleton(array $overrides = []): array
	{
		return array_merge([
			'name'               => 'Demo Mod',
			'filename'           => 'Class-Demo',
			'hooks'              => ['integrate_load_theme', 'integrate_pre_log_stats'],
			'author'             => 'Bugo',
			'namespace'          => 'Bugo',
			'email'              => 'bugo@example.test',
			'readmes'            => ['english' => 'Text {mod_name}'],
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

	private function lint(string $code): void
	{
		$file = tempnam(sys_get_temp_dir(), 'smm_') . '.php';

		try {
			file_put_contents($file, $code);

			exec(
				escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1',
				$output,
				$exitCode
			);

			$this->assertSame(
				0,
				$exitCode,
				implode("\n", $output) . "\n---\n" . $code
			);
		} finally {
			@unlink($file);
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

	#[Test]
	public function generatesValidClassWithRegisteredHooks(): void
	{
		$skeleton  = $this->skeleton();
		$classname = 'Demo';
		$snakeName = 'demo';

		$generator = new Generator();
		$result    = $generator->generate($skeleton, $classname, $snakeName);

		$this->assertArrayHasKey('content', $result);
		$this->assertArrayHasKey('tasks', $result);
		$this->assertIsArray($result['tasks']);

		$this->lint($result['content']);

		$this->assertStringContainsString('class Demo', $result['content']);
		$this->assertStringContainsString('public function hooks', $result['content']);
		$this->assertStringContainsString('function loadTheme', $result['content']);
		$this->assertStringContainsString('function preLogStats', $result['content']);
		$this->assertStringContainsString('add_integration_function', $result['content']);
	}

	#[Test]
	public function generatesSettingsArea2WithConfigVarsAndSaveRoutine(): void
	{
		$skeleton = $this->skeleton([
			'settings_area' => 2,
			'hooks'         => ['integrate_general_mod_settings', 'integrate_modify_modifications', 'integrate_credits'],
			'options'       => [
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
					'default'      => 'a|b',
					'variants'     => 'a|b',
					'translations' => ['english' => 'Choice'],
				],
			],
			'add_copyrights' => true,
		]);

		$generator = new Generator();
		$result    = $generator->generate($skeleton, 'Demo', 'demo');

		$this->lint($result['content']);

		$this->assertStringContainsString('function settings', $result['content']);
		$this->assertStringContainsString("'check', 'demo_enable_feature'", $result['content']);
		$this->assertStringContainsString("'select', 'demo_choice'", $result['content']);
		$this->assertStringContainsString('saveDBSettings', $result['content']);
		$this->assertStringContainsString('credits', $result['content']);
	}

	#[Test]
	public function generatesScheduledAndBackgroundTasksAndPropagatesCallable(): void
	{
		$skeleton = $this->skeleton([
			'make_dir'        => true,
			'hooks'           => ['integrate_admin_areas'],
			'scheduled_tasks' => [
				0 => [
					'slug'         => 'daily_cleanup',
					'names'        => ['english' => 'Daily cleanup'],
					'descriptions' => ['english' => 'Cleans stale data'],
					'regularity'   => 0,
				],
			],
			'background_tasks' => [
				0 => [
					'classname'  => 'BackgroundTask',
					'regularity' => 1,
				],
			],
			'legacy_tasks' => [
				0 => [
					'method'     => 'runWeekly',
					'regularity' => 1,
				],
			],
		]);

		$generator = new Generator();
		$result    = $generator->generate($skeleton, 'Demo', 'demo');

		$this->assertCount(2, $result['tasks']);

		foreach ($result['tasks'] as $task) {
			$this->assertArrayHasKey('content', $task);
			$this->assertArrayHasKey('filename', $task);
			$this->lint($task['content']);

			$this->assertStringContainsString("extends SMF_BackgroundTask", $task['content']);
			$this->assertStringContainsString("function execute", $task['content']);
		}

		$this->assertArrayHasKey('scheduled_tasks', $skeleton);
		$this->assertArrayHasKey('background_tasks', $skeleton);
		$this->assertArrayHasKey('callable', $skeleton['scheduled_tasks'][0]);
		$this->assertStringContainsString(
			'\\\\Bugo\\\\Demo\\\\Tasks\\\\DailyCleanup::execute',
			$skeleton['scheduled_tasks'][0]['callable']
		);
		$this->assertArrayHasKey('callable', $skeleton['background_tasks'][0]);
		$this->assertSame(
			'\\\\Bugo\\\\Demo\\\\Tasks\\\\BackgroundTask',
			$skeleton['background_tasks'][0]['callable']
		);

		$this->lint($result['content']);
		$this->assertStringContainsString('runWeekly', $result['content']);
		$this->assertStringContainsString('integrate_weekly_maintenance', $result['content']);
		$this->assertStringContainsString("add_integration_function", $result['content']);
	}

	#[Test]
	public function generalModSettingsHookAppendsConfigVarsInsteadOfOverwriting(): void
	{
		$skeleton = $this->skeleton([
			'settings_area' => 1,
			'hooks'         => ['integrate_general_mod_settings'],
			'options'       => [
				0 => [
					'name'         => 'enable_feature',
					'type'         => 'check',
					'default'      => true,
					'variants'     => '',
					'translations' => ['english' => 'Enable feature'],
				],
			],
		]);

		$generator = new Generator();
		$result    = $generator->generate($skeleton, 'Demo', 'demo');

		$this->lint($result['content']);

		$this->assertStringNotContainsString('$config_vars = [', $result['content']);
		$this->assertStringContainsString('$config_vars[] = [\'check\', \'demo_enable_feature\'];', $result['content']);
	}

	#[Test]
	public function zeroDefaultsAreKeptWhileFalseAndEmptyOnesAreSkipped(): void
	{
		$skeleton = $this->skeleton([
			'settings_area' => 2,
			'hooks'         => ['integrate_modify_modifications'],
			'options'       => [
				0 => [
					'name'         => 'enable',
					'type'         => 'check',
					'default'      => false,
					'variants'     => '',
					'translations' => [],
				],
				1 => [
					'name'         => 'limit',
					'type'         => 'int',
					'default'      => '0',
					'variants'     => '',
					'translations' => [],
				],
				2 => [
					'name'         => 'ratio',
					'type'         => 'float',
					'default'      => '0.0',
					'variants'     => '',
					'translations' => [],
				],
				3 => [
					'name'         => 'prefix',
					'type'         => 'text',
					'default'      => '0',
					'variants'     => '',
					'translations' => [],
				],
				4 => [
					'name'         => 'choice',
					'type'         => 'select',
					'default'      => '',
					'variants'     => 'a|b',
					'translations' => [],
				],
			],
		]);

		$generator = new Generator();
		$result    = $generator->generate($skeleton, 'Demo', 'demo');

		$this->lint($result['content']);

		$this->assertStringContainsString('$addSettings[\'demo_limit\'] = 0;', $result['content']);
		$this->assertStringContainsString('$addSettings[\'demo_ratio\'] = 0.0;', $result['content']);
		$this->assertStringContainsString('$addSettings[\'demo_prefix\'] = \'0\';', $result['content']);
		$this->assertStringNotContainsString('$addSettings[\'demo_enable\']', $result['content']);
		$this->assertStringNotContainsString('$addSettings[\'demo_choice\']', $result['content']);
	}

	#[Test]
	public function multipleSelectDefaultIsNormalizedToJsonArray(): void
	{
		$skeleton = $this->skeleton([
			'settings_area' => 2,
			'hooks'         => ['integrate_modify_modifications'],
			'options'       => [
				0 => [
					'name'         => 'tags',
					'type'         => 'select-multiple',
					'default'      => 'b | a , a',
					'variants'     => 'a|b|c',
					'translations' => [],
				],
			],
		]);

		$generator = new Generator();
		$result    = $generator->generate($skeleton, 'Demo', 'demo');

		$this->lint($result['content']);

		$this->assertStringContainsString('$addSettings[\'demo_tags\'] = \'["b","a"]\';', $result['content']);
	}

	#[Test]
	public function defaultSettingsAreInitializedInEverySettingsArea(): void
	{
		$areas = [
			1 => ['integrate_general_mod_settings'],
			2 => ['integrate_admin_areas'],
			3 => ['integrate_admin_areas'],
		];

		foreach ($areas as $settingsArea => $hooks) {
			foreach (['2.1', '3.0'] as $targetVersion) {
				$skeleton = $this->skeleton([
					'settings_area'      => $settingsArea,
					'smf_target_version' => $targetVersion,
					'hooks'              => $hooks,
					'options'            => [
						0 => [
							'name'         => 'limit',
							'type'         => 'int',
							'default'      => '0',
							'variants'     => '',
							'translations' => [],
						],
					],
				]);

				$generator = new Generator();
				$result    = $generator->generate($skeleton, 'Demo', 'demo');

				$this->lint($result['content']);

				$this->assertStringContainsString('$addSettings[\'demo_limit\'] = 0;', $result['content']);
				$this->assertStringContainsString(
					$targetVersion === '3.0' ? 'isset(Config::$modSettings[\'demo_limit\'])' : "isset(\$modSettings['demo_limit'])",
					$result['content']
				);
				$this->assertStringContainsString(
					$targetVersion === '3.0' ? 'Config::updateModSettings($addSettings);' : 'updateSettings($addSettings);',
					$result['content']
				);

				if ($settingsArea === 1 && $targetVersion !== '3.0') {
					$this->assertStringContainsString('global $modSettings;', $result['content']);
				}
			}
		}
	}

	#[Test]
	public function area1HookDeclaresGlobalsRequiredByGeneratedBody(): void
	{
		// A select option references $txt for its label, and a default needs $modSettings
		$skeleton = $this->skeleton([
			'settings_area' => 1,
			'hooks'         => ['integrate_general_mod_settings'],
			'options'       => [
				0 => [
					'name'         => 'choice',
					'type'         => 'select',
					'default'      => 'a',
					'variants'     => 'a|b',
					'translations' => [],
				],
			],
		]);

		$generator = new Generator();
		$result    = $generator->generate($skeleton, 'Demo', 'demo');

		$this->lint($result['content']);
		$this->assertStringContainsString('global $modSettings, $txt;', $result['content']);

		// Without defaults only the label global is required
		$skeleton['options'][0]['default'] = '';

		$result = $generator->generate($skeleton, 'Demo', 'demo');

		$this->lint($result['content']);
		$this->assertStringContainsString('global $txt;', $result['content']);
		$this->assertStringNotContainsString('$modSettings', $result['content']);

		// An unticked check option needs no globals at all
		$skeleton['options'] = [
			0 => [
				'name'         => 'enable',
				'type'         => 'check',
				'default'      => false,
				'variants'     => '',
				'translations' => [],
			],
		];

		$result = $generator->generate($skeleton, 'Demo', 'demo');

		$this->lint($result['content']);
		$this->assertStringNotContainsString('global $', $result['content']);
		$this->assertStringNotContainsString('$addSettings', $result['content']);
	}

	#[Test]
	public function noDefaultSettingsBlockWithoutExplicitDefaults(): void
	{
		$areas = [
			1 => ['integrate_general_mod_settings'],
			2 => ['integrate_admin_areas'],
			3 => ['integrate_admin_areas'],
		];

		foreach ($areas as $settingsArea => $hooks) {
			$skeleton = $this->skeleton([
				'settings_area' => $settingsArea,
				'hooks'         => $hooks,
				'options'       => [
					0 => [
						'name'         => 'enable',
						'type'         => 'check',
						'default'      => false,
						'variants'     => '',
						'translations' => [],
					],
					1 => [
						'name'         => 'note',
						'type'         => 'text',
						'default'      => '',
						'variants'     => '',
						'translations' => [],
					],
				],
			]);

			$generator = new Generator();
			$result    = $generator->generate($skeleton, 'Demo', 'demo');

			$this->lint($result['content']);

			$this->assertStringNotContainsString('$addSettings', $result['content']);
			$this->assertStringNotContainsString('updateSettings(', $result['content']);
			$this->assertStringNotContainsString('global $modSettings', $result['content']);
		}
	}

	#[Test]
	public function selectOptionsUseFlatLangKeysForBothTargetVersions(): void
	{
		foreach (['2.1', '3.0'] as $targetVersion) {
			$expectedLabel = ($targetVersion === '3.0' ? 'Lang::$txt' : '$txt') . "['demo_choice_set']";

			$skeleton = $this->skeleton([
				'settings_area'      => 2,
				'smf_target_version' => $targetVersion,
				'hooks'              => ['integrate_general_mod_settings'],
				'options'            => [
					0 => [
						'name'         => 'choice',
						'type'         => 'select',
						'default'      => 'a',
						'variants'     => 'a|b',
						'translations' => ['english' => 'Choice'],
					],
					1 => [
						'name'         => 'multi_choice',
						'type'         => 'select-multiple',
						'default'      => 'a',
						'variants'     => 'a|b',
						'translations' => ['english' => 'Multi choice'],
					],
				],
			]);

			$generator = new Generator();
			$result    = $generator->generate($skeleton, 'Demo', 'demo');

			$this->lint($result['content']);

			$this->assertStringContainsString(
				"'select', 'demo_choice', " . $expectedLabel . ", 'multiple' => false",
				$result['content']
			);
			$this->assertStringContainsString(
				"'select', 'demo_multi_choice', " . ($targetVersion === '3.0' ? 'Lang::$txt' : '$txt')
				. "['demo_multi_choice_set'], 'multiple' => true",
				$result['content']
			);
			$this->assertStringNotContainsString('$txt[\'demo\']', $result['content']);
		}
	}

	#[Test]
	public function selectOptionsUseLangTxtOnSmf3(): void
	{
		$skeleton = $this->skeleton([
			'settings_area'      => 2,
			'smf_target_version' => '3.0',
			'hooks'              => ['integrate_general_mod_settings'],
			'options'            => [
				0 => [
					'name'         => 'choice',
					'type'         => 'select',
					'default'      => 'a',
					'variants'     => 'a|b',
					'translations' => ['english' => 'Choice'],
				],
			],
		]);

		$generator = new Generator();
		$result    = $generator->generate($skeleton, 'Demo', 'demo');

		$this->lint($result['content']);

		$this->assertStringContainsString(
			"'select', 'demo_choice', Lang::\$txt['demo_choice_set'], 'multiple' => false",
			$result['content']
		);
	}

	#[Test]
	public function settingsArea3UsesStringLiteralsAndSourcedirPaths(): void
	{
		foreach ([false, true] as $makeDir) {
			foreach (['2.1', '3.0'] as $targetVersion) {
				foreach ([false, true] as $makeTemplate) {
					$skeleton = $this->skeleton([
						'settings_area'      => 3,
						'smf_target_version' => $targetVersion,
						'make_dir'           => $makeDir,
						'make_template'      => $makeTemplate,
					]);

					$generator = new Generator();
					$result    = $generator->generate($skeleton, 'Demo', 'demo');

					$this->lint($result['content']);

					$this->assertStringNotContainsString('loadTemplate(Demo)', $result['content']);
					$this->assertStringNotContainsString('dirname(__DIR__)', $result['content']);

					if ($makeTemplate) {
						$this->assertStringContainsString(
							$targetVersion === '3.0' ? "Theme::loadTemplate('Demo')" : "loadTemplate('Demo')",
							$result['content']
						);
					} else {
						$this->assertStringNotContainsString('loadTemplate(', $result['content']);
					}

					if ($targetVersion !== '3.0') {
						$this->assertStringContainsString(
							"require_once \$sourcedir . '/ManageSettings.php';",
							$result['content']
						);
						$this->assertStringContainsString(
							"require_once \$sourcedir . '/ManageServer.php';",
							$result['content']
						);
					}
				}
			}
		}
	}

	#[Test]
	public function callbackOptionsLoadTemplateWithoutMakeTemplate(): void
	{
		$areas = [
			1 => ['integrate_general_mod_settings'],
			2 => ['integrate_admin_areas'],
			3 => ['integrate_admin_areas'],
		];

		foreach ($areas as $settingsArea => $hooks) {
			foreach (['2.1', '3.0'] as $targetVersion) {
				$skeleton = $this->skeleton([
					'settings_area'      => $settingsArea,
					'smf_target_version' => $targetVersion,
					'hooks'              => $hooks,
					'options'            => [
						0 => [
							'name'         => 'custom_block',
							'type'         => 'callback',
							'default'      => '',
							'variants'     => '',
							'translations' => ['english' => 'Custom block'],
						],
					],
				]);

				$generator = new Generator();
				$result    = $generator->generate($skeleton, 'Demo', 'demo');

				$this->lint($result['content']);

				$this->assertSame(['demo_custom_block'], $skeleton['callbacks']);
				$this->assertStringContainsString(
					$targetVersion === '3.0' ? "Theme::loadTemplate('Demo')" : "loadTemplate('Demo')",
					$result['content']
				);
			}
		}
	}

	#[Test]
	public function callbackTemplateIsInstalledAndRemovedWithoutMakeTemplate(): void
	{
		$skeleton = $this->skeleton([
			'settings_area' => 2,
			'hooks'         => ['integrate_admin_areas'],
			'options'       => [
				0 => [
					'name'         => 'custom_block',
					'type'         => 'callback',
					'default'      => '',
					'variants'     => '',
					'translations' => ['english' => 'Custom block'],
				],
			],
		]);

		$classname = 'Demo';
		$snakeName = 'demo';

		$generator = new Generator();
		$result    = $generator->generate($skeleton, $classname, $snakeName);

		$base = sys_get_temp_dir() . '/smm_builder_' . bin2hex(random_bytes(4));
		mkdir($base, 0777, true);

		try {
			$path = $base . '/demo_' . $skeleton['version'];

			$builder = new Builder([
				'skeleton'     => $skeleton,
				'classname'    => $classname,
				'snakename'    => $snakeName,
				'path'         => $path,
				'allowed_base' => $base,
			]);

			$builder->create($result['content']);

			$templateFile = $path . '/Themes/default/' . $classname . '.template.php';

			$this->assertFileExists($templateFile);

			$template = (string) file_get_contents($templateFile);

			$this->assertStringContainsString('function template_callback_demo_custom_block()', $template);
			$this->lint($template);

			$method = new ReflectionMethod($builder, 'preparePackageInfo');
			$method->invoke($builder);

			package_flush_cache();

			$manifest = (string) file_get_contents($path . '/package-info.xml');

			$this->assertStringContainsString('Themes/default/Demo.template.php', $manifest);
			$this->assertStringContainsString('$themedir/Demo.template.php', $manifest);
		} finally {
			self::deleteDirectory($base);
		}
	}

	#[Test]
	public function smf3TargetRegistersHooksViaIntegrationHook(): void
	{
		$skeleton = $this->skeleton([
			'smf_target_version' => '3.0',
			'hooks'              => ['integrate_load_theme'],
		]);

		$generator = new Generator();
		$result    = $generator->generate($skeleton, 'Demo', 'demo');

		$this->lint($result['content']);

		$this->assertStringContainsString("IntegrationHook::add('integrate_load_theme'", $result['content']);
		$this->assertStringNotContainsString('add_integration_function(', $result['content']);
	}

	#[Test]
	public function mixedTargetGeneratesBothVersionBranches(): void
	{
		$skeleton = $this->skeleton([
			'settings_area'      => 2,
			'smf_target_version' => '2.1/3.0',
			'hooks'              => ['integrate_admin_areas', 'integrate_modify_modifications', 'integrate_credits'],
			'options'            => [
				0 => [
					'name'         => 'choice',
					'type'         => 'select',
					'default'      => 'a',
					'variants'     => 'a|b',
					'translations' => ['english' => 'Choice'],
				],
			],
			'add_copyrights' => true,
		]);

		$generator = new Generator();
		$result    = $generator->generate($skeleton, 'Demo', 'demo');

		$this->lint($result['content']);

		// Both hook registration APIs are available behind a runtime check
		$this->assertStringContainsString("version_compare(SMF_VERSION, '3.0', '>=')", $result['content']);
		$this->assertStringContainsString("IntegrationHook::add('integrate_admin_areas'", $result['content']);
		$this->assertStringContainsString("add_integration_function('integrate_admin_areas'", $result['content']);

		// The import of the modern hook API is in place
		$this->assertStringContainsString('use SMF\IntegrationHook;', $result['content']);

		// Both settings save routines are present
		$this->assertStringContainsString('ACP::saveDBSettings($save_vars);', $result['content']);
		$this->assertStringContainsString('saveDBSettings($save_vars);', $result['content']);

		// Both labels of the select option are present
		$this->assertStringContainsString("Lang::\$txt['demo_choice_set']", $result['content']);
		$this->assertStringContainsString("\$txt['demo_choice_set']", $result['content']);

		// The copyright hook offers both APIs as well
		$this->assertStringContainsString("Utils::\$context['copyrights']['mods'][]", $result['content']);
		$this->assertStringContainsString("\$context['copyrights']['mods'][]", $result['content']);

		// Every generated method carries exactly one version check: one in
		// hooks() for all registrations, one in each versioned method
		$this->assertSame(
			4,
			substr_count($result['content'], "version_compare(SMF_VERSION, '3.0', '>=')")
		);
	}

	#[Test]
	public function mixedTargetTasksDeclareBothClassVariants(): void
	{
		$skeleton = $this->skeleton([
			'make_dir'           => true,
			'smf_target_version' => '2.1/3.0',
			'scheduled_tasks'    => [
				0 => [
					'slug'         => 'daily_cleanup',
					'names'        => [],
					'descriptions' => [],
					'regularity'   => 0,
				],
			],
			'background_tasks'   => [
				0 => [
					'classname'  => 'HeavyJob',
					'regularity' => 1,
				],
			],
			'legacy_tasks'       => [
				0 => [
					'method'     => 'runWeekly',
					'regularity' => 1,
				],
			],
		]);

		$generator = new Generator();
		$result    = $generator->generate($skeleton, 'Demo', 'demo');

		$this->assertCount(2, $result['tasks']);

		foreach ($result['tasks'] as $task) {
			$this->lint($task['content']);
			$this->assertStringContainsString("version_compare(SMF_VERSION, '3.0', '>=')", $task['content']);
		}

		// The scheduled task extends the parent of the running SMF version
		$scheduled = $result['tasks']['daily_cleanup']['content'];
		$this->assertStringContainsString('extends ScheduledTask', $scheduled);
		$this->assertStringContainsString('extends SMF_BackgroundTask', $scheduled);

		// The background task requeues itself via the API of the running version
		$background = $result['tasks']['HeavyJob']['content'];
		$this->assertStringContainsString('Db::$db->insert(\'insert\',', $background);
		$this->assertStringContainsString("\$smcFunc['db_insert']('insert',", $background);

		// The example runner offers both database APIs too
		$this->lint($result['content']);
		$this->assertStringContainsString('Db::$db->insert(\'insert\',', $result['content']);
		$this->assertStringContainsString("\$smcFunc['db_insert']('insert',", $result['content']);

		// The legacy task method declares its globals only for SMF 2.1
		$this->assertStringContainsString('global $smcFunc;', $result['content']);
	}

	#[Test]
	public function mixedTargetKeepsIndentationOfMultiLineBodies(): void
	{
		$skeleton = $this->skeleton([
			'smf_target_version' => '2.1/3.0',
			'hooks'              => ['integrate_alert_types'],
		]);

		$generator = new Generator();
		$result    = $generator->generate($skeleton, 'Demo', 'demo');

		$this->lint($result['content']);

		// Inside the version check, every line of the multi-line body keeps its
		// own nesting: array items sit one level deeper than the statement, in
		// both branches
		$expected = "\n\n\t\t\t\$alert_types['demo']['some_action'] = [\n"
			. "\t\t\t\t'alert' => 'yes', // or 'never'\n"
			. "\t\t\t\t'email' => 'yes', // or 'never'\n"
			. "\t\t\t\t'permission' => [\n"
			. "\t\t\t\t\t'name'     => 'some_permission',\n"
			. "\t\t\t\t\t'is_board' => false,\n"
			. "\t\t\t\t]\n"
			. "\t\t\t];";

		$this->assertSame(2, substr_count($result['content'], $expected));
	}

	#[Test]
	public function pureTargetsKeepIndentationOfMultiLineBodies(): void
	{
		foreach (['2.1', '3.0'] as $targetVersion) {
			$skeleton = $this->skeleton([
				'smf_target_version' => $targetVersion,
				'hooks'              => ['integrate_alert_types'],
			]);

			$generator = new Generator();
			$result    = $generator->generate($skeleton, 'Demo', 'demo');

			$this->lint($result['content']);

			// Without the version check the body sits at the method level, and
			// the array items are still one level deeper than the statement
			$expected = "\n\n\t\t\$alert_types['demo']['some_action'] = [\n"
				. "\t\t\t'alert' => 'yes', // or 'never'\n"
				. "\t\t\t'email' => 'yes', // or 'never'\n"
				. "\t\t\t'permission' => [\n"
				. "\t\t\t\t'name'     => 'some_permission',\n"
				. "\t\t\t\t'is_board' => false,\n"
				. "\t\t\t]\n"
				. "\t\t];";

			$this->assertSame(1, substr_count($result['content'], $expected));
		}
	}

	#[Test]
	public function generatedPackageFilesCanBeWrittenByBuilder(): void
	{
		foreach (['2.1', '3.0', '2.1/3.0'] as $targetVersion) {
			$skeleton = $this->skeleton([
				'make_dir'           => true,
				'smf_target_version' => $targetVersion,
				'tables'             => [
					0 => [
						'name'    => 'example_table',
						'columns' => [
							0 => ['name' => 'id', 'type' => 'int', 'null' => false, 'size' => 10, 'auto' => true, 'default' => ''],
							1 => ['name' => 'name', 'type' => 'varchar', 'null' => false, 'size' => 255, 'auto' => false, 'default' => ''],
							2 => ['name' => 'notes', 'type' => 'mediumtext', 'null' => true, 'size' => 0, 'auto' => false, 'default' => ''],
						],
					],
				],
				'make_readme' => true,
				'title'       => ['english' => 'Builder Demo'],
				'description' => ['english' => 'Builder description'],
			]);

			$classname = 'Demo';
			$snakeName = 'demo';

			$generator = new Generator();
			$result    = $generator->generate($skeleton, $classname, $snakeName);

			foreach ($result['tasks'] as $task) {
				$this->lint($task['content']);
			}

			$base = sys_get_temp_dir() . '/smm_builder_' . bin2hex(random_bytes(4));
			mkdir($base, 0777, true);

			try {
				$GLOBALS['modSettings'] = ['package_disable_cache' => 1];

				$builder = new Builder([
					'skeleton'  => $skeleton,
					'classname' => $classname,
					'snakename' => $snakeName,
					'path'      => $base . '/demo_' . $skeleton['version'],
				]);

				$builder->create($result['content'])
					->createTasks($result['tasks']);

				package_flush_cache();
				$mainFile = $base . '/demo_' . $skeleton['version'] . '/Sources/' . $classname . '/Integration.php';
				$this->assertFileExists($mainFile);
				$this->lint(file_get_contents($mainFile));

				$databaseFile = $base . '/demo_' . $skeleton['version'] . '/database.php';
				$this->assertFileExists($databaseFile);
				$this->lint(file_get_contents($databaseFile));

				$this->assertStringContainsString("'primary'", file_get_contents($databaseFile));
			} finally {
				$this->deleteDirectory($base);
			}
		}
	}
}
