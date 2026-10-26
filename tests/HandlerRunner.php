<?php declare(strict_types=1);

use SMF\Actions\Admin\ACP;
use SMF\Config;
use SMF\IntegrationHook;
use SMF\Lang;
use SMF\Menu;
use SMF\Theme;
use SMF\User;
use SMF\Utils;

// Executes the key handlers of one generated package inside a separate PHP
// process, so the recording stubs cannot collide with the real SMF helpers
// that the PHPUnit bootstrap has already loaded. Everything the handlers do
// is collected into one report and returned as a single JSON line.

if (! defined('SMF')) {
	define('SMF', true);
}

if (! defined('SMM_NAME')) {
	define('SMM_NAME', 'Simple Mod Maker');
}

// The mixed-target packages execute the branch of the running version
if (! defined('SMF_VERSION')) {
	define('SMF_VERSION', '3.0.0');
}

function loadLanguage($template_name, $lang = '', $fatal = true, $force_reload = false): void
{
	$GLOBALS['txt']['smm_tab_example'] = 'Section %d';
}

function loadTemplate(string $name): void
{
	$GLOBALS['smm_report']['theme'][] = ['loadTemplate', $name];
}

function checkSession(string $type = 'post'): void
{
}

function saveDBSettings(array $configVars): void
{
	$GLOBALS['smm_report']['save_db_settings'][] = $configVars;
}

function prepareDBSettingContext(array $configVars): void
{
	$GLOBALS['smm_report']['prepare_db_setting_context'][] = $configVars;
}

function redirectexit(string $url = ''): void
{
	$GLOBALS['smm_report']['redirectexit'][] = $url;
}

function add_integration_function($hook, $function, $file = false, $permanent = true): void
{
	$GLOBALS['smm_report']['registrations'][$hook] = $function;
}

function updateSettings(array $settings): void
{
	$GLOBALS['smm_report']['update_settings'][] = $settings;
}

function loadGeneralSettingParameters(array &$subActions, string $defaultAction = ''): void
{
	$GLOBALS['context']['sub_action'] = (string) ($_REQUEST['sa'] ?? $defaultAction);
}

function call_helper(mixed $callable): void
{
	if (is_callable($callable)) {
		call_user_func($callable);
	}
}

class HandlerRunner
{
	public static function run(array $params): void
	{
		try {
			$report = self::execute($params);
		} catch (Throwable $e) {
			$report = ['fatal' => $e->getMessage() . "\n" . $e->getTraceAsString()];
		}

		echo json_encode($report);
	}

	private static function execute(array $params): array
	{
		require_once __DIR__ . '/Smf3Stubs.php';

		$GLOBALS['context']     = [
			'admin_menu_name' => 'admin_menu',
			'admin_menu'      => ['tab_data' => []],
			'page_title'      => 'Demo',
		];
		$GLOBALS['txt']         = $params['txt'];
		$GLOBALS['scripturl']   = 'https://smf.test/index.php';
		$GLOBALS['modSettings'] = [];
		$GLOBALS['sourcedir']   = $params['sourcedir'];

		Lang::$txt           = $params['txt'];
		Config::$modSettings = [];
		Config::$scripturl   = 'https://smf3.test/index.php';
		Utils::$context      = ['page_title' => 'Demo'];

		Menu::$loaded = ['admin' => new class
		{
			public array $tab_data = [];
		}];

		User::$me = new class
		{
			public function checkSession(): void
			{
			}
		};

		require $params['main_file'];

		$fqcn      = $params['fqcn'];
		$instance  = new $fqcn();
		$normalize = static fn (mixed $callable): mixed => is_array($callable) && is_object($callable[0] ?? null)
			? [$callable[0]::class, $callable[1]]
			: $callable;

		$GLOBALS['smm_report']['methods'] = [];

		foreach ($params['hook_methods'] as $hook => $method) {
			$GLOBALS['smm_report']['methods'][$hook] = method_exists($instance, $method);
		}

		$instance->hooks();

		if ($params['legacy']) {
			$GLOBALS['smm_report']['registrations'] ??= [];
		} else {
			$GLOBALS['smm_report']['registrations'] = array_column(IntegrationHook::$calls, 1, 0);
		}

		if ($params['settings_area'] === 1) {
			$configVars = [['title', 'smm_existing']];

			$instance->generalModSettings($configVars);

			$GLOBALS['smm_report']['area1']['config_vars'] = $configVars;
		} elseif ($params['settings_area'] === 2) {
			$GLOBALS['smm_report']['area2']['config_vars'] = $instance->settings(true);

			$_GET['save'] = '1';

			$instance->settings();

			$adminAreas = [];

			$instance->adminAreas($adminAreas);

			$GLOBALS['smm_report']['area2']['admin_areas'] = $adminAreas;

			$subActions = [];

			$instance->modifyModifications($subActions);

			$GLOBALS['smm_report']['area2']['sub_actions'] = array_map($normalize, $subActions);

			$languageFiles  = [];
			$includeFiles   = [];
			$settingsSearch = [];

			$instance->adminSearch($languageFiles, $includeFiles, $settingsSearch);

			$GLOBALS['smm_report']['area2']['admin_search'] = [
				$languageFiles,
				$includeFiles,
				array_map(static fn (array $entry): array => [$normalize($entry[0]), $entry[1]], $settingsSearch),
			];
		} elseif ($params['settings_area'] === 3) {
			$instance->settings();

			$GLOBALS['smm_report']['area3']['section1_return'] = $instance->sectionExample1(true);

			$adminAreas = [];

			$instance->adminAreas($adminAreas);

			// The area carries a callable of the settings method
			$area = $adminAreas['config']['areas'][$params['snake_name']] ?? [];

			if (isset($area['function'])) {
				$area['function'] = $normalize($area['function']);
			}

			$GLOBALS['smm_report']['area3']['admin_areas'] = ['config' => ['areas' => [$params['snake_name'] => $area]]];
		}

		if ($params['make_dir']) {
			$classMap = [];

			$instance->autoload($classMap);

			$GLOBALS['smm_report']['class_map'] = $classMap;
		}

		$GLOBALS['smm_report']['theme'] = array_merge($GLOBALS['smm_report']['theme'] ?? [], Theme::$calls);

		$GLOBALS['smm_report']['modern'] = [
			'config' => Config::$calls,
			'acp'    => ACP::$calls,
			'utils'  => Utils::$calls,
		];

		return $GLOBALS['smm_report'];
	}
}
