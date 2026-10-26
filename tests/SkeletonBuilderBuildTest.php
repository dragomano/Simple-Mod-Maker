<?php declare(strict_types=1);

namespace Tests;

use Bugo\SimpleModMaker\Hook;
use Bugo\SimpleModMaker\SkeletonBuilder;
use PHPUnit\Framework\TestCase;

/**
 * The whole build flow is driven through the public build() method: the
 * posted form data must survive the validation and end up in the complete
 * skeleton that the generator consumes.
 */
class SkeletonBuilderBuildTest extends TestCase
{
	private array $tempGlobals = [];

	protected function setUp(): void
	{
		global $context, $txt, $modSettings;

		$this->tempGlobals = ['context', 'txt', 'modSettings'];

		$context     = ['post_errors' => []];
		$modSettings = [];
		$txt         = [];
		$_POST       = [];
		$_REQUEST    = [];

		$this->loadLangStrings();

		$context['smm_languages'] = [
			['filename' => 'english', 'name' => 'English'],
			['filename' => 'russian', 'name' => 'Русский'],
		];
	}

	protected function tearDown(): void
	{
		foreach ($this->tempGlobals as $name) {
			unset($GLOBALS[$name]);
		}

		unset($_POST, $_REQUEST);
	}

	public function testFullPostDataProducesTheCompleteSkeleton(): void
	{
		global $context, $modSettings;

		$_POST = $this->fullPostData();

		$modSettings = [
			'smm_mod_author' => 'Bugo',
			'smm_mod_email'  => 'bugo@example.test',
			'smm_readme'     => '{"english":"Readme {mod_name}"}',
		];

		$skeleton = (new SkeletonBuilder())->build();

		$this->assertSame('Demo Mod', $skeleton['name']);
		$this->assertSame('Class-Demo', $skeleton['filename']);
		$this->assertSame('MyVendor\\MyMods', $skeleton['namespace']);
		$this->assertSame('Bugo', $skeleton['author']);
		$this->assertSame('bugo@example.test', $skeleton['email']);
		$this->assertSame('1.0', $skeleton['version']);
		$this->assertSame(['english' => 'Readme {mod_name}'], $skeleton['readmes']);
		$this->assertSame('own', $skeleton['license']);
		$this->assertSame('Own license', $skeleton['license_data']['short_name']);
		$this->assertTrue($skeleton['make_dir']);
		$this->assertSame(1, $skeleton['settings_area']);

		$this->assertSame([
			0 => [
				'name'         => 'enable_feature',
				'type'         => 'check',
				'default'      => true,
				'variants'     => '',
				'translations' => [
					'english' => 'Enable feature',
					'russian' => 'Включить',
				],
			],
			1 => [
				'name'         => 'choice',
				'type'         => 'select',
				'default'      => 'a',
				'variants'     => 'a|b',
				'translations' => ['russian' => 'Выбор'],
			],
			2 => [
				'name'         => 'custom_block',
				'type'         => 'callback',
				'default'      => '',
				'variants'     => '',
				'translations' => [],
			],
		], $skeleton['options']);

		$this->assertSame([
			[
				'name'    => 'demo_table',
				'columns' => [
					['name' => 'id', 'type' => 'int', 'null' => false, 'size' => 10, 'auto' => true, 'default' => ''],
					['name' => 'title', 'type' => 'varchar', 'null' => false, 'size' => 100, 'auto' => false, 'default' => 'none'],
					['name' => 'notes', 'type' => 'text', 'null' => true, 'size' => 0, 'auto' => false, 'default' => ''],
				],
			],
		], $skeleton['tables']);

		$this->assertSame([
			0 => [
				'slug'         => 'daily_cleanup',
				'names'        => ['russian' => 'Очистка'],
				'descriptions' => ['russian' => 'Чистит данные'],
				'regularity'   => '0',
			],
		], $skeleton['scheduled_tasks']);

		$this->assertSame([
			0 => ['classname' => 'CleanupBackground', 'regularity' => '0'],
		], $skeleton['background_tasks']);

		$this->assertSame([
			0 => ['method' => 'runWeekly', 'regularity' => '1'],
		], $skeleton['legacy_tasks']);

		// The weekly legacy task, the tasks themselves, the settings area
		// and the copyrights are added to the manually selected hooks
		$this->assertSame([
			'integrate_load_theme',
			'integrate_buffer',
			Hook::WEEKLY_MAINTENANCE,
			Hook::AUTOLOAD,
			Hook::GENERAL_MOD_SETTINGS,
			Hook::CREDITS,
		], $skeleton['hooks']);

		$this->assertSame(['english', 'russian'], $skeleton['languages']);
		$this->assertSame(['english' => 'Demo', 'russian' => 'Демо'], $skeleton['title']);
		$this->assertSame(['english' => 'Desc', 'russian' => 'Описание'], $skeleton['description']);

		$this->assertSame($skeleton, $context['smm_skeleton']);
	}

	public function testEmptyRequestBuildsTheDefaultSkeleton(): void
	{
		global $context;

		$skeleton = (new SkeletonBuilder())->build();

		$this->assertSame(SMM_MODNAME_DEFAULT, $skeleton['name']);
		$this->assertSame('', $skeleton['filename']);
		$this->assertSame('0.1', $skeleton['version']);
		$this->assertSame('Unknown', $skeleton['author']);
		$this->assertSame(SMM_VENDOR_DEFAULT, $skeleton['namespace']);
		$this->assertSame('mit', $skeleton['license']);
		$this->assertSame('The MIT License', $skeleton['license_data']['full_name']);
		$this->assertSame(0, $skeleton['settings_area']);
		$this->assertFalse($skeleton['make_dir']);
		$this->assertNull($skeleton['readmes']);
		$this->assertSame([], $skeleton['options']);
		$this->assertSame([], $skeleton['tables']);
		$this->assertSame([], $skeleton['scheduled_tasks']);
		$this->assertSame([], $skeleton['background_tasks']);
		$this->assertSame([], $skeleton['legacy_tasks']);
		$this->assertSame(['english'], $skeleton['languages']);
		$this->assertSame([], $skeleton['title']);
		$this->assertSame([], $skeleton['description']);
		$this->assertSame([], $skeleton['hooks']);
		$this->assertSame($skeleton, $context['smm_skeleton']);
	}

	public function testOptionsForceTheSettingsAreaAndItsHook(): void
	{
		$_POST = [
			'save'           => '1',
			'name'           => 'Demo',
			'filename'       => 'Class-Demo',
			'settings_area'  => '0',
			'option_names'   => [0 => 'enable_feature'],
			'option_types'   => [0 => 'check'],
		];

		$skeleton = (new SkeletonBuilder())->build();

		$this->assertSame(1, $skeleton['settings_area']);
		$this->assertSame([Hook::GENERAL_MOD_SETTINGS], $skeleton['hooks']);
	}

	public function testLegacyTasksPickTheMaintenanceHookByTheirRegularity(): void
	{
		$_POST = [
			'save'                    => '1',
			'name'                    => 'Demo',
			'filename'                => 'Class-Demo',
			'legacy_task_methods'     => [0 => 'runDaily', 1 => 'runWeekly'],
			'legacy_task_regularities' => [1 => '1'],
		];

		$skeleton = (new SkeletonBuilder())->build();

		$this->assertSame([Hook::DAILY_MAINTENANCE, Hook::WEEKLY_MAINTENANCE], $skeleton['hooks']);
		$this->assertSame([
			0 => ['method' => 'runDaily', 'regularity' => ''],
			1 => ['method' => 'runWeekly', 'regularity' => '1'],
		], $skeleton['legacy_tasks']);
		$this->assertFalse($skeleton['make_dir']);
		$this->assertNotContains(Hook::AUTOLOAD, $skeleton['hooks']);
	}

	public function testEmptySubmittedEntriesAreDroppedEverywhere(): void
	{
		$_POST = [
			'save'              => '1',
			'name'              => 'Demo',
			'filename'          => 'Class-Demo',
			'option_names'      => [0 => '', 1 => 'good_option'],
			'option_types'      => [0 => 'check', 1 => 'text'],
			'table_names'       => [0 => '', 1 => 'good_table'],
			'column_names'      => [1 => [0 => 'title']],
			'column_types'      => [1 => [0 => 'varchar']],
			'task_slugs'        => [0 => '', 1 => 'good_task'],
			'task_regularities' => [1 => '2'],
		];

		$skeleton = (new SkeletonBuilder())->build();

		$this->assertSame(['good_option'], array_column($skeleton['options'], 'name'));
		$this->assertSame(['good_table'], array_column($skeleton['tables'], 'name'));
		$this->assertSame(['good_task'], array_column($skeleton['scheduled_tasks'], 'slug'));
		$this->assertSame(1, $skeleton['settings_area']);
		$this->assertSame(['english'], $skeleton['languages']);
	}

	public function testEmptyTasksAndColumnlessTablesAreSkipped(): void
	{
		$_POST = [
			'save'                         => '1',
			'name'                         => 'Demo',
			'filename'                     => 'Class-Demo',
			'settings_area'                => '2',
			'table_names'                  => [0 => '', 1 => 'good_table', 2 => 'empty_table'],
			'column_names'                 => [1 => [0 => 'title']],
			'column_types'                 => [1 => [0 => 'varchar']],
			'background_task_classnames'   => [0 => '', 1 => 'CleanupBackground'],
			'background_task_regularities' => [1 => '1'],
			'legacy_task_methods'          => [0 => '', 1 => 'runDaily'],
			'legacy_task_regularities'     => [1 => '0'],
		];

		$skeleton = (new SkeletonBuilder())->build();

		$this->assertSame([
			['name' => 'good_table', 'columns' => [
				['name' => 'title', 'type' => 'varchar', 'null' => false, 'size' => 255, 'auto' => false, 'default' => ''],
			]],
			['name' => 'empty_table', 'columns' => []],
		], $skeleton['tables']);

		$this->assertSame(
			[1 => ['classname' => 'CleanupBackground', 'regularity' => '1']],
			$skeleton['background_tasks']
		);

		$this->assertSame(
			[1 => ['method' => 'runDaily', 'regularity' => '0']],
			$skeleton['legacy_tasks']
		);

		// The daily legacy task, the background task and the second settings
		// area with its own hooks are added to the empty selection
		$this->assertSame([
			Hook::DAILY_MAINTENANCE,
			Hook::AUTOLOAD,
			Hook::ADMIN_AREAS,
			Hook::ADMIN_SEARCH,
			Hook::MODIFY_MODIFICATIONS,
		], $skeleton['hooks']);
	}

	private function fullPostData(): array
	{
		return [
			'save'                        => '1',
			'name'                        => 'Demo Mod',
			'filename'                    => 'Class-Demo',
			'namespace'                   => 'MyVendor\\MyMods',
			'hooks'                       => ['integrate_load_theme', 'integrate_buffer'],
			'version'                     => '1.0',
			'site'                        => 'https://example.test',
			'license'                     => 'own',
			'min_php_version'             => '8.2',
			'smf_target_version'          => '2.1/3.0',
			'settings_area'               => '1',
			'title_english'               => 'Demo',
			'description_english'         => 'Desc',
			'title_russian'               => 'Демо',
			'description_russian'         => 'Описание',
			'option_names'                => [0 => 'enable_feature', 1 => 'choice', 2 => 'custom_block'],
			'option_types'                => [0 => 'check', 1 => 'select', 2 => 'callback'],
			'option_defaults'             => [0 => 'on', 1 => 'a'],
			'option_variants'             => [1 => 'a|b'],
			'option_translations'         => [
				'english' => [0 => 'Enable feature'],
				'russian' => [0 => 'Включить', 1 => 'Выбор'],
			],
			'table_names'                 => [0 => 'demo_table'],
			'column_names'                => [0 => [0 => 'id', 1 => 'title', 2 => 'notes']],
			'column_types'                => [0 => [0 => 'int', 1 => 'varchar', 2 => 'text']],
			'column_auto'                 => [0 => [0 => 'on']],
			'column_sizes'                => [0 => [1 => '100']],
			'column_null'                 => [0 => [2 => 'on']],
			'column_defaults'             => [0 => [1 => 'none']],
			'task_slugs'                  => [0 => 'daily_cleanup'],
			'task_regularities'           => [0 => '0'],
			'task_names'                  => ['russian' => [0 => 'Очистка']],
			'task_descriptions'           => ['russian' => [0 => 'Чистит данные']],
			'background_task_classnames'  => [0 => 'CleanupBackground'],
			'background_task_regularities' => [0 => '0'],
			'legacy_task_methods'         => [0 => 'runWeekly'],
			'legacy_task_regularities'    => [0 => '1'],
			'make_dir'                    => 'on',
			'use_lang_dir'                => 'on',
			'make_template'               => 'on',
			'make_script'                 => 'on',
			'make_css'                    => 'on',
			'make_readme'                 => 'on',
			'add_copyrights'              => 'on',
		];
	}

	private function loadLangStrings(): void
	{
		global $txt;

		require dirname(__DIR__) . '/src/Themes/default/languages/SimpleModMaker/.english.php';

		// Core SMF strings that the tested flows read alongside the mod's own
		$txt['email']   = 'user@example.test';
		$txt['website'] = 'Website';
	}
}
