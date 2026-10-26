<?php declare(strict_types=1);

namespace Tests;

use Bugo\SimpleModMaker\Handler;
use Bugo\SimpleModMaker\Hook;
use Bugo\SimpleModMaker\Hooks\HookFactory;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

/**
 * The generator page is exercised through the public generator() method:
 * a plain view request renders the form, a saved submission runs the
 * validation, and only a clean submission reaches the package builder.
 * The builder itself is driven to a controlled failure, because its
 * successful download terminates the process (that path lives in
 * HandlerPackageBuildTest).
 */
class HandlerGeneratorTest extends TestCase
{
	private Handler $handler;

	private string $tempBase;

	private array $tempGlobals = [];

	protected function setUp(): void
	{
		global $context, $txt, $modSettings, $scripturl, $sc, $packagesdir;

		$this->handler   = new Handler();
		$this->tempBase  = sys_get_temp_dir() . '/smm_handler_' . bin2hex(random_bytes(4));
		$this->tempGlobals = ['context', 'txt', 'modSettings', 'scripturl', 'packagesdir'];

		$txt         = $this->langStrings();
		$scripturl   = 'https://example.test/index.php';
		$modSettings = [];
		$packagesdir = $this->tempBase . '/Packages';

		$context = [
			'post_errors'     => [],
			'admin_menu_name' => 'admin_menu',
			'admin_menu'      => [],
			'smm_languages'   => [
				['filename' => 'english', 'name' => 'English'],
				['filename' => 'russian', 'name' => 'Русский'],
			],
		];

		$sc       = 'test-check';
		$_SESSION = ['session_var' => 'sc_session_var'];
		$_GET     = $_POST = $_REQUEST = [];

		$GLOBALS['smm_assets']           = [];
		$GLOBALS['smm_settings_updates'] = [];
	}

	protected function tearDown(): void
	{
		deltree($this->tempBase);

		foreach ($this->tempGlobals as $name) {
			unset($GLOBALS[$name]);
		}

		unset($_SESSION, $_GET, $_POST, $_REQUEST, $GLOBALS['smm_assets'], $GLOBALS['smm_settings_updates']);
	}

	public function testViewRequestRendersTheFormWithoutGeneratingAnything(): void
	{
		global $context, $modSettings, $packagesdir;

		mkdir($packagesdir, 0777, true);

		$modSettings['smm_hooks']        = 'integrate_user_info,integrate_load_theme';
		$modSettings['smm_mod_namespace'] = 'StoredVendor';

		$this->handler->generator();

		$this->assertSame(SMM_NAME . ' - ' . $this->langStrings()['smm_generator'], $context['page_title']);
		$this->assertSame($this->langStrings()['smm_generator'], $context['page_area_title']);
		$this->assertSame('https://example.test/index.php?action=admin;area=smm;sa=generator', $context['canonical_url']);
		$this->assertSame([
			'title'       => SMM_NAME,
			'description' => $this->langStrings()['smm_add_desc'],
		], $context['admin_menu']['tab_data']);
		$this->assertSame(['tinyint', 'int', 'mediumint', 'varchar', 'text', 'mediumtext'], $context['smm_column_types']);
		$this->assertSame('modification_post', $context['sub_template']);

		// The remembered vendor is pre-filled into the namespace field
		$this->assertSame('StoredVendor', $context['smm_skeleton']['namespace']);
		$this->assertSame(SMM_MODNAME_DEFAULT, $context['smm_skeleton']['name']);
		$this->assertSame(SMM_MODNAME_DEFAULT, $context['posting_fields']['name']['input']['attributes']['value']);

		// The hook list combines the stored hooks with the skeleton ones
		$this->assertSame([
			['text' => 'integrate_load_theme', 'value' => 'integrate_load_theme'],
			['text' => 'integrate_user_info', 'value' => 'integrate_user_info'],
		], $context['smm_hook_list']['data']);
		$this->assertSame(
			$context['smm_hook_list']['select_options'],
			$context['posting_fields']['hooks']['input']['options']
		);

		// A fresh form sequence is registered for the rendered form
		$this->assertNotEmpty($context['form_sequence_number']);
		$this->assertArrayHasKey($context['form_sequence_number'], $_SESSION['smm_form_sequences']);

		// The CDN library and the local assets are queued
		$this->assertCount(2, $GLOBALS['smm_assets']['js']);
		$this->assertCount(2, $GLOBALS['smm_assets']['css']);

		// Nothing was generated or remembered
		$this->assertSame([], glob($packagesdir . '/*', GLOB_ONLYDIR));
		$this->assertSame([], $GLOBALS['smm_settings_updates']);
	}

	public function testSavedSubmissionWithErrorsSkipsTheGeneration(): void
	{
		global $context, $txt, $packagesdir;

		mkdir($packagesdir, 0777, true);

		$_POST                   = array_merge($this->fullPostData(), ['filename' => 'Bad Name!']);
		$_POST['sc_session_var'] = $GLOBALS['sc'];

		$this->handler->generator();

		$this->assertSame([$txt['smm_error_no_valid_filename']], $context['post_errors']);

		// The form is still rendered for the corrected resubmission
		$this->assertSame('modification_post', $context['sub_template']);
		$this->assertArrayHasKey('name', $context['posting_fields']);

		// The erroneous data never reaches the builder
		$this->assertSame([], glob($packagesdir . '/*', GLOB_ONLYDIR));
		$this->assertSame([], $GLOBALS['smm_settings_updates']);
	}

	public function testBuilderFailureIsSurfacedAsAFatalError(): void
	{
		global $modSettings, $packagesdir;

		// The builder needs a writable directory: a file in its place makes
		// the package creation fail after the settings were remembered
		mkdir($this->tempBase, 0777, true);
		file_put_contents($packagesdir, 'not a directory');

		$_POST                   = $this->fullPostData();
		$_POST['sc_session_var'] = $GLOBALS['sc'];
		$modSettings['smm_hooks'] = 'integrate_user_info';

		try {
			$this->handler->generator();

			$this->fail('The builder failure must surface as a fatal error.');
		} catch (RuntimeException $e) {
			$this->assertStringContainsString('Cannot create the directory', $e->getMessage());
		}

		// The used hooks and the vendor are remembered before the build
		$this->assertSame([
			['smm_hooks' => 'integrate_load_theme,integrate_buffer,integrate_weekly_maintenance,'
				. 'integrate_autoload,integrate_general_mod_settings,integrate_credits,integrate_user_info'],
			['smm_mod_namespace' => 'MyVendor\\MyMods'],
		], $GLOBALS['smm_settings_updates']);
	}

	public function testShouldGeneratePackageRequiresCleanDataAndSave(): void
	{
		$method = new ReflectionMethod($this->handler, 'shouldGeneratePackage');

		$_POST['save'] = '';

		$this->assertTrue($method->invoke($this->handler, ['post_errors' => []], ['name' => 'Demo']));
		$this->assertFalse($method->invoke($this->handler, ['post_errors' => ['Oops']], ['name' => 'Demo']));
		$this->assertFalse($method->invoke($this->handler, ['post_errors' => []], []));

		unset($_POST['save']);

		$this->assertFalse($method->invoke($this->handler, ['post_errors' => []], ['name' => 'Demo']));
	}

	public function testClassnamesAreConvertedToSnakeCase(): void
	{
		$method = new ReflectionMethod($this->handler, 'toSnakeCase');

		$this->assertSame('demo', $method->invoke($this->handler, 'Demo'));
		$this->assertSame('demo_mod', $method->invoke($this->handler, 'DemoMod'));
		$this->assertSame('my_mod', $method->invoke($this->handler, 'MyMod'));
	}

	public function testUsedHooksAreMergedWithTheStoredOnes(): void
	{
		global $modSettings;

		$modSettings['smm_hooks'] = 'integrate_user_info';

		(new ReflectionMethod($this->handler, 'rememberUsedHooks'))->invoke($this->handler, [
			'hooks' => ['integrate_actions', 'integrate_actions'],
		]);

		$this->assertSame(
			[['smm_hooks' => 'integrate_actions,integrate_user_info']],
			$GLOBALS['smm_settings_updates']
		);
	}

	public function testEmptyHookListIsNotStored(): void
	{
		(new ReflectionMethod($this->handler, 'rememberUsedHooks'))->invoke($this->handler, ['hooks' => []]);

		$this->assertSame([], $GLOBALS['smm_settings_updates']);
	}

	public function testNewNamespaceIsRememberedForTheNextBuild(): void
	{
		global $modSettings;

		$method = new ReflectionMethod($this->handler, 'rememberNamespace');

		$modSettings['smm_mod_namespace'] = 'OldVendor';

		$method->invoke($this->handler, ['namespace' => 'NewVendor']);

		$this->assertSame([['smm_mod_namespace' => 'NewVendor']], $GLOBALS['smm_settings_updates']);

		// The same vendor is not stored again
		$GLOBALS['smm_settings_updates'] = [];

		$method->invoke($this->handler, ['namespace' => 'OldVendor']);
		$method->invoke($this->handler, ['namespace' => '']);

		$this->assertSame([], $GLOBALS['smm_settings_updates']);
	}

	public function testBasicFieldsCarryTheirDefaultsAndOptions(): void
	{
		global $txt;

		$txt = $this->langStrings();

		$fields = (new ReflectionMethod($this->handler, 'buildBasicFields'))->invoke($this->handler, [
			'name'               => 'Demo Mod',
			'namespace'          => 'MyVendor\\MyMods',
			'version'            => '1.0',
			'site'               => 'https://example.test',
			'license'            => 'own',
			'min_php_version'    => '8.2',
			'smf_target_version' => '2.1/3.0',
		], $txt);

		$this->assertSame(
			['name', 'filename', 'namespace', 'hooks', 'version', 'site', 'license', 'min_php_version', 'smf_target_version'],
			array_keys($fields)
		);

		$this->assertSame('Demo Mod', $fields['name']['input']['attributes']['value']);
		$this->assertSame(SMM_FILENAME_PATTERN, $fields['filename']['input']['attributes']['pattern']);
		$this->assertSame("'Class-' + className.replace(/ /g, '')", $fields['filename']['input']['attributes'][':value']);
		$this->assertSame(SMM_VENDOR_DEFAULT, $fields['namespace']['input']['attributes']['placeholder']);

		// The licenses are offered with the own one localized, and the
		// skeleton license is preselected
		$this->assertSame('own', $fields['license']['input']['options']['Own license']['value']);

		$selected = array_filter(
			array_column($fields['license']['input']['options'], 'selected', 'value'),
			fn(bool $isSelected): bool => $isSelected
		);

		$this->assertSame(['own' => true], $selected);
		$this->assertArrayHasKey('Own license', $fields['license']['input']['options']);

		// The mixed target version is preselected in its turn
		$selected = array_filter(
			array_column($fields['smf_target_version']['input']['options'], 'selected', 'value'),
			fn(bool $isSelected): bool => $isSelected
		);

		$this->assertSame(['2.1/3.0' => true], $selected);

		foreach (array_keys($fields) as $field) {
			$this->assertSame('basic', $fields[$field]['input']['tab'], $field);
		}
	}

	public function testTitleFieldsAreBuiltPerLanguageAndSupportRtl(): void
	{
		global $txt;

		$txt = $this->langStrings();

		$method = new ReflectionMethod($this->handler, 'buildSettingsFields');

		$skeleton = [
			'settings_area' => 2,
			'title'         => ['english' => 'Demo', 'russian' => 'Демо'],
			'description'   => ['russian' => 'Описание'],
		];

		$fields = $method->invoke($this->handler, $skeleton, $txt, ['smm_languages' => $GLOBALS['context']['smm_languages']]);
		$html   = $fields['title']['input']['html'];

		$this->assertSame('settings', $fields['title']['input']['tab']);
		$this->assertSame('settings', $fields['settings_area']['input']['tab']);

		// Each language gets its navigation link and its own inputs
		$this->assertStringContainsString('x-on:click.prevent="tab = \'english\'"', $html);
		$this->assertStringContainsString('x-on:click.prevent="tab = \'russian\'"', $html);
		$this->assertStringContainsString('name="title_english"', $html);
		$this->assertStringContainsString('value="Demo"', $html);
		$this->assertStringContainsString('name="description_russian"', $html);
		$this->assertStringContainsString('value="Описание"', $html);
		$this->assertStringContainsString($this->langStrings()['smm_mod_title_default'], $html);
		$this->assertStringContainsString('floatleft', $html);

		// The second area of the settings placement is preselected
		$selected = array_filter(
			array_column($fields['settings_area']['input']['options'], 'selected', 'value'),
			fn(bool $isSelected): bool => $isSelected
		);

		$this->assertSame([2 => true], $selected);

		// The right-to-left forums drop the left float of the navigation
		$fields = $method->invoke($this->handler, $skeleton, $txt, [
			'smm_languages' => $GLOBALS['context']['smm_languages'],
			'right_to_left' => '1',
		]);

		$this->assertStringNotContainsString('floatleft', $fields['title']['input']['html']);
	}

	public function testPackageFieldsMirrorTheSkeletonFlags(): void
	{
		global $txt;

		$txt = $this->langStrings();

		$fields = (new ReflectionMethod($this->handler, 'buildPackageFields'))->invoke($this->handler, [
			'make_dir'       => true,
			'use_lang_dir'   => false,
			'make_template'  => true,
			'make_script'    => false,
			'make_css'       => false,
			'make_readme'    => true,
			'add_copyrights' => false,
		], $txt);

		$this->assertSame(
			['make_dir', 'use_lang_dir', 'make_template', 'make_script', 'make_css', 'make_readme', 'add_copyrights'],
			array_keys($fields)
		);

		foreach ($fields as $field => $data) {
			$this->assertSame('checkbox', $data['input']['type'], $field);
			$this->assertSame($data['input']['attributes']['checked'], in_array($field, ['make_dir', 'make_template', 'make_readme']), $field);
		}

		$this->assertSame($txt['smm_make_dir_subtext'], $fields['make_dir']['input']['after']);
		$this->assertSame($txt['smm_add_copyrights_subtext'], $fields['add_copyrights']['input']['after']);
		$this->assertArrayNotHasKey('after', $fields['use_lang_dir']['input']);
	}

	public function testPostProcessingWrapsSubtextsAndDefaultsTabs(): void
	{
		$fields = [
			'text_field' => [
				'input' => ['type' => 'text', 'after' => 'plain hint', 'tab' => 'basic'],
			],
			'check_field' => [
				'input' => ['type' => 'checkbox', 'after' => 'check hint'],
			],
			'number_field' => [
				'input' => ['type' => 'number', 'after' => 'number hint'],
			],
			'untabbed' => [
				'input' => ['type' => 'text'],
			],
		];

		(new ReflectionMethod($this->handler, 'postProcessFields'))->invokeArgs($this->handler, [&$fields]);

		$this->assertSame(
			'<div class="descbox alternative2 smalltext">plain hint</div>',
			$fields['text_field']['input']['after']
		);
		$this->assertSame('basic', $fields['text_field']['input']['tab']);

		$this->assertSame('checkbox', $fields['check_field']['input']['attributes']['class']);
		$this->assertSame(
			'<label class="label" for="check_field"></label><span class="descbox alternative2 smalltext">check hint</span>',
			$fields['check_field']['input']['after']
		);
		$this->assertSame(
			'<span class="descbox alternative2 smalltext">number hint</span>',
			$fields['number_field']['input']['after']
		);

		$this->assertSame('package', $fields['untabbed']['input']['tab']);
	}

	public function testHookListCombinesTheFactoryHooksWithTheBuiltinOnes(): void
	{
		$list = (new ReflectionMethod($this->handler, 'getHookList'))->invoke($this->handler);

		$expected = array_unique(array_merge(
			HookFactory::getAvailableHooks(),
			[
				Hook::FORUM_STATS,
				Hook::HELP_ADMIN,
				Hook::LOAD_THEME,
				Hook::POST_END,
				Hook::PRE_CSS_OUTPUT,
				Hook::THEME_CONTEXT,
				Hook::USER_INFO,
			]
		));

		sort($expected);

		$this->assertSame($expected, $list);
	}

	private function fullPostData(): array
	{
		return [
			'save'                         => '1',
			'name'                         => 'Demo Mod',
			'filename'                     => 'Class-Demo',
			'namespace'                    => 'MyVendor\\MyMods',
			'hooks'                        => ['integrate_load_theme', 'integrate_buffer'],
			'version'                      => '1.0',
			'site'                         => 'https://example.test',
			'license'                      => 'own',
			'min_php_version'              => '8.2',
			'smf_target_version'           => '2.1/3.0',
			'settings_area'                => '1',
			'title_english'                => 'Demo',
			'description_english'          => 'Desc',
			'title_russian'                => 'Демо',
			'description_russian'          => 'Описание',
			'option_names'                 => [0 => 'enable_feature', 1 => 'choice', 2 => 'custom_block'],
			'option_types'                 => [0 => 'check', 1 => 'select', 2 => 'callback'],
			'option_defaults'              => [0 => 'on', 1 => 'a'],
			'option_variants'              => [1 => 'a|b'],
			'option_translations'          => [
				'english' => [0 => 'Enable feature'],
				'russian' => [0 => 'Включить', 1 => 'Выбор'],
			],
			'table_names'                  => [0 => 'demo_table'],
			'column_names'                 => [0 => [0 => 'id', 1 => 'title', 2 => 'notes']],
			'column_types'                 => [0 => [0 => 'int', 1 => 'varchar', 2 => 'text']],
			'column_auto'                  => [0 => [0 => 'on']],
			'column_sizes'                 => [0 => [1 => '100']],
			'column_null'                  => [0 => [2 => 'on']],
			'column_defaults'              => [0 => [1 => 'none']],
			'task_slugs'                   => [0 => 'daily_cleanup'],
			'task_regularities'            => [0 => '0'],
			'task_names'                   => ['russian' => [0 => 'Очистка']],
			'task_descriptions'            => ['russian' => [0 => 'Чистит данные']],
			'background_task_classnames'   => [0 => 'CleanupBackground'],
			'background_task_regularities' => [0 => '0'],
			'legacy_task_methods'          => [0 => 'runWeekly'],
			'legacy_task_regularities'     => [0 => '1'],
			'make_dir'                     => 'on',
			'use_lang_dir'                 => 'on',
			'make_template'                => 'on',
			'make_script'                  => 'on',
			'make_css'                     => 'on',
			'make_readme'                  => 'on',
			'add_copyrights'               => 'on',
		];
	}

	private function langStrings(): array
	{
		global $txt;

		$txt = [];

		require dirname(__DIR__) . '/src/Themes/default/languages/SimpleModMaker/.english.php';

		// Core SMF strings that the tested flows read alongside the mod's own
		$txt['email']   = 'user@example.test';
		$txt['website'] = 'Website';

		return $txt;
	}
}
