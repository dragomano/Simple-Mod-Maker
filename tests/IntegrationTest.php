<?php declare(strict_types=1);

namespace Tests;

use Closure;
use RuntimeException;
use Bugo\SimpleModMaker\Hook;
use Bugo\SimpleModMaker\Integration;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * The admin integration is exercised through its public methods: the hook
 * registration, the menu area, and the whole basic settings page including
 * its saving path. The settings page requires the SMF distribution files
 * (ManageSettings, ManageServer, Subs-Editor) from the mod's Sources
 * neighborhood, so this test creates throwaway stubs of them.
 */
class IntegrationTest extends TestCase
{
	private array $createdStubs = [];

	private array $tempGlobals = [];

	protected function setUp(): void
	{
		global $context, $txt, $modSettings, $user_info, $language, $scripturl, $sc;

		$this->tempGlobals = ['context', 'txt', 'modSettings', 'user_info', 'language', 'scripturl', 'smm_available_languages'];

		$this->createSmfStubs();

		$txt         = $this->langStrings();
		$modSettings = [];
		$user_info   = ['name' => 'Tester', 'email' => 'tester@example.test', 'language' => 'english'];
		$language    = 'english';
		$scripturl   = 'https://example.test/index.php';
		$sc          = 'test-check';

		$context = [
			'admin_menu_name' => 'admin_menu',
			'admin_menu'      => [],
			'page_title'      => '',
		];

		$_GET = $_POST = $_REQUEST = [];

		$GLOBALS['smm_report']               = [];
		$GLOBALS['smm_settings_updates']     = [];
		$GLOBALS['smm_integration_functions'] = [];
		$GLOBALS['smm_languages_loaded']     = [];
		$GLOBALS['smm_templates_loaded']     = [];
		$GLOBALS['smm_available_languages']  = [
			'english' => ['filename' => 'english', 'name' => 'English'],
			'russian' => ['filename' => 'russian', 'name' => 'Русский'],
			'french'  => ['filename' => 'french', 'name' => 'Français'],
		];
	}

	protected function tearDown(): void
	{
		$this->removeSmfStubs();

		foreach ($this->tempGlobals as $name) {
			unset($GLOBALS[$name]);
		}

		unset(
			$_GET,
			$_POST,
			$_REQUEST,
			$_SESSION,
			$GLOBALS['smm_report'],
			$GLOBALS['smm_settings_updates'],
			$GLOBALS['smm_integration_functions'],
			$GLOBALS['smm_languages_loaded'],
			$GLOBALS['smm_templates_loaded']
		);
	}

	public function testConstructorRegistersTheAdminAreasHook(): void
	{
		new Integration();

		$this->assertSame(
			[Hook::ADMIN_AREAS => [Integration::class . '::adminAreas#']],
			$GLOBALS['smm_integration_functions']
		);
	}

	public function testAdminAreasAddsTheModArea(): void
	{
		global $txt;

		$integration = new Integration();

		$admin_areas = ['config' => ['areas' => []]];

		$integration->adminAreas($admin_areas);

		$area = $admin_areas['config']['areas']['smm'];

		$this->assertSame(SMM_NAME, $area['label']);
		$this->assertSame('maintain', $area['icon']);
		$this->assertInstanceOf(Closure::class, $area['function']);
		$this->assertSame([
			'basic'     => [$txt['smm_basic']],
			'generator' => [$txt['smm_generator']],
		], $area['subsections']);

		// The mod's own language strings are loaded for the labels
		$this->assertSame([['SimpleModMaker/', '']], $GLOBALS['smm_languages_loaded']);
	}

	public function testSettingsRendersTheBasicSettingsPage(): void
	{
		global $context, $txt;

		(new Integration())->settings();

		// The default sub action is the basic settings
		$this->assertSame('basic', $context['sub_action']);
		$this->assertSame(SMM_NAME . ' - ' . $txt['smm_basic'], $context['page_title']);
		$this->assertSame(
			'https://example.test/index.php?action=admin;area=smm;sa=basic;save',
			$context['post_url']
		);
		$this->assertSame([
			'title'       => SMM_NAME,
			'description' => $txt['smm_desc'],
			'tabs'        => ['basic' => [], 'generator' => []],
		], $context['admin_menu']['tab_data']);

		$this->assertSame(['SimpleModMaker'], $GLOBALS['smm_templates_loaded']);

		// The forum languages are prepared for the readme editors
		$this->assertSame(['english'], array_keys($context['smm_languages']));

		// The missing defaults are stored on the first visit
		$updates = $GLOBALS['smm_settings_updates'];

		$this->assertCount(1, $updates);
		$this->assertSame('Tester', $updates[0]['smm_mod_author']);
		$this->assertSame('tester@example.test', $updates[0]['smm_mod_email']);
		$this->assertSame(
			['english' => $txt['smm_readme_default']],
			json_decode($updates[0]['smm_readme'], true)
		);

		// The readme description lists the available variables
		$this->assertSame(
			'The following variables are available:'
			. '<br><ul class="bbc_list"><li>{<strong>mod_name</strong>} - The mod name</li>'
			. '<li>{<strong>author</strong>} - Author name</li>'
			. '<li>{<strong>description</strong>} - Description</li>'
			. '<li>{<strong>license</strong>} - License</ul>',
			$txt['smm_readme_desc']
		);

		// A rich editor is prepared per language
		$this->assertSame(['english' => 'smm_readme_english'], $context['smm_readme_editor']);
		$this->assertSame([[
			'id'                 => 'smm_readme_english',
			'value'              => '',
			'height'             => '150px',
			'width'              => '100%',
			'disable_smiley_box' => true,
		]], $GLOBALS['smm_report']['richedit']);

		// The settings context is prepared for rendering, nothing was saved
		$configVars = $GLOBALS['smm_report']['prepare_db_setting_context'][0];

		$this->assertSame(['title', 'smm_basic'], $configVars[0]);
		$this->assertContains(['text', 'smm_mod_author'], $configVars);
		$this->assertContains(['email', 'smm_mod_email', 'label' => $txt['email']], $configVars);
		$this->assertContains(['callback', 'smm_readme_editor'], $configVars);
		$this->assertArrayNotHasKey('save_db_settings', $GLOBALS['smm_report']);
	}

	public function testSettingsSaveStoresTheReadmeAndRedirects(): void
	{
		global $context, $modSettings;

		$modSettings = ['smm_mod_author' => 'Bugo', 'smm_mod_email' => 'bugo@example.test'];

		$_REQUEST['sa'] = 'basic';
		$_GET['save']   = '1';

		$_SESSION                = ['session_var' => 'sc_session_var'];
		$_POST                   = ['smm_readme_english' => 'My readme text'];
		$_POST['sc_session_var'] = $GLOBALS['sc'];

		try {
			(new Integration())->settings();

			$this->fail('The successful save must redirect away.');
		} catch (RuntimeException $e) {
			$this->assertSame('action=admin;area=smm;sa=basic', $e->getMessage());
		}

		// The readme of every posted language lands in the saved settings
		$saved = $GLOBALS['smm_report']['save_db_settings'][0];

		$this->assertSame(['large_text', 'smm_readme'], end($saved));

		// The values themselves travel through the POST payload
		$this->assertSame(['english' => 'My readme text'], json_decode($_POST['smm_readme'], true));

		// The redirect ends the flow before the settings context is rendered
		$this->assertArrayNotHasKey('prepare_db_setting_context', $GLOBALS['smm_report']);
	}

	public function testBasicSettingsCanReturnTheirConfigVars(): void
	{
		global $context, $txt;

		$context['smm_languages'] = ['english' => ['filename' => 'english', 'name' => 'English']];

		$configVars = (new Integration())->basicSettings(true);

		$this->assertSame(['title', 'smm_basic'], $configVars[0]);
		$this->assertSame(['text', 'smm_mod_author'], $configVars[1]);
		$this->assertSame(['email', 'smm_mod_email', 'label' => $txt['email']], $configVars[2]);
		$this->assertSame(['title', 'smm_readme'], $configVars[3]);
		$this->assertSame(['desc', 'smm_readme_desc'], $configVars[4]);
		$this->assertSame(['callback', 'smm_readme_editor'], $configVars[5]);
		$this->assertArrayNotHasKey('save_db_settings', $GLOBALS['smm_report']);
	}

	public function testLanguagesAreLimitedWithoutTheUserLanguage(): void
	{
		global $context, $modSettings, $language;

		$language = 'russian';

		$method = new ReflectionMethod(Integration::class, 'prepareForumLanguages');

		$method->invoke(new Integration());

		$this->assertSame(['english', 'russian'], array_keys($context['smm_languages']));

		// The forum default language of english needs no extra entry
		$language = 'english';

		$method->invoke(new Integration());

		$this->assertSame(['english'], array_keys($context['smm_languages']));
	}

	public function testLanguagesAreMergedWithTheUserLanguage(): void
	{
		global $context, $modSettings, $user_info;

		$modSettings['userLanguage'] = '1';
		$user_info['language']       = 'french';

		(new ReflectionMethod(Integration::class, 'prepareForumLanguages'))->invoke(new Integration());

		$this->assertSame(['english', 'french', 'russian'], array_keys($context['smm_languages']));
	}

	public function testDefaultSettingsFillOnlyTheMissingValues(): void
	{
		global $context, $modSettings;

		$context['smm_languages'] = [['filename' => 'english', 'name' => 'English']];

		$modSettings = ['smm_mod_author' => 'Bugo', 'smm_mod_email' => 'bugo@example.test', 'smm_readme' => '{}'];

		(new ReflectionMethod(Integration::class, 'addDefaultSettings'))->invoke(new Integration());

		$this->assertSame([[]], $GLOBALS['smm_settings_updates']);
	}

	public function testReadmeEditorsAreCreatedPerLanguage(): void
	{
		global $context, $modSettings;

		$context['smm_languages'] = [
			['filename' => 'english', 'name' => 'English'],
			['filename' => 'russian', 'name' => 'Русский'],
		];

		$modSettings['smm_readme'] = '{"english":"EN text","russian":"RU text"}';

		(new ReflectionMethod(Integration::class, 'prepareBbcEditor'))->invoke(new Integration());

		$this->assertSame([
			'english' => 'smm_readme_english',
			'russian' => 'smm_readme_russian',
		], $context['smm_readme_editor']);

		$this->assertSame('EN text', $GLOBALS['smm_report']['richedit'][0]['value']);
		$this->assertSame('RU text', $GLOBALS['smm_report']['richedit'][1]['value']);
	}

	private function createSmfStubs(): void
	{
		$sourcesDir = dirname((string) (new ReflectionClass(Integration::class))->getFileName(), 2);

		foreach (['ManageSettings.php', 'ManageServer.php', 'Subs-Editor.php'] as $file) {
			$path = $sourcesDir . '/' . $file;

			if (is_file($path)) {
				continue;
			}

			// A real SMF installation provides these files next to the mod;
			// the helper functions themselves live in the test bootstrap
			file_put_contents($path, "<?php\n\n// Test stub created by IntegrationTest.\n");

			$this->createdStubs[] = $path;
		}
	}

	private function removeSmfStubs(): void
	{
		foreach ($this->createdStubs as $path) {
			@unlink($path);
		}

		$this->createdStubs = [];
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
