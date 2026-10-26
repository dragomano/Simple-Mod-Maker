<?php declare(strict_types=1);

// Warnings, notices and deprecations must not be masked: the PHPUnit
// configuration turns every one of them into a test failure
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/vendor/autoload.php';

// The dependencies of the mod itself (nette) are installed into its own
// vendor directory, just like in a real SMF installation
require_once dirname(__DIR__) . '/src/Sources/SimpleModMaker/vendor/autoload.php';

// The minimal context of a working page, so the flows under test always
// start from the same baseline
$GLOBALS['context'] = [
	'post_errors' => [],
];

// Define SMF functions that are used in tests. The suite must run without
// the SMF distribution files, so every helper gets a self-sufficient stub.
if (! function_exists('add_integration_function')) {
	function add_integration_function($hook, $function, $file = false, $permanent = true) {
		$GLOBALS['smm_integration_functions'][$hook][] = $function;
	}
}

if (! function_exists('loadJavaScriptFile')) {
	function loadJavaScriptFile($fileName, $params = []) {
		$GLOBALS['smm_assets']['js'][] = [$fileName, $params];
	}
}

if (! function_exists('loadCSSFile')) {
	function loadCSSFile($fileName, $params = []) {
		$GLOBALS['smm_assets']['css'][] = [$fileName, $params];
	}
}

if (! function_exists('JavaScriptEscape')) {
	function JavaScriptEscape($string): string
	{
		return addslashes($string);
	}
}

if (! function_exists('smf_json_decode')) {
	function smf_json_decode($json, $assoc = false) {
		return json_decode($json, $assoc);
	}
}

if (! function_exists('cache_get_data')) {
	function cache_get_data($key, $ttl = 120) {
		return null; // Always return null for tests
	}
}

if (! function_exists('cache_put_data')) {
	function cache_put_data($key, $data, $ttl = 120) {
		// Mock function for tests
	}
}

if (! function_exists('get_integration_hooks')) {
	function get_integration_hooks(): array
	{
		return []; // Return empty array for tests
	}
}

if (! function_exists('loadLanguage')) {
	function loadLanguage($template_name, $lang = '', $fatal = true, $force_reload = false) {
		$GLOBALS['smm_languages_loaded'][] = [$template_name, $lang];
		$GLOBALS['txt']['smm_tab_example'] = 'Section %d';
	}
}

if (! function_exists('loadTemplate')) {
	function loadTemplate(string $name): void {
		$GLOBALS['smm_templates_loaded'][] = $name;
	}
}

if (! function_exists('updateSettings')) {
	function updateSettings($settings) {
		$GLOBALS['smm_settings_updates'][] = $settings;
	}
}

if (! function_exists('redirectexit')) {
	function redirectexit($url = ''): never {
		$GLOBALS['smm_redirects'][] = $url;

		// The real helper never returns: it ends the page flow
		throw new RuntimeException((string) $url);
	}
}

// The forum language list is configurable per test, because the flow under
// test slices it differently depending on the user language settings
if (! function_exists('getLanguages')) {
	function getLanguages(): array {
		return $GLOBALS['smm_available_languages'] ?? [
			'english' => ['filename' => 'english', 'name' => 'English'],
		];
	}
}

if (! function_exists('saveDBSettings')) {
	function saveDBSettings(array $config_vars): void {
		$GLOBALS['smm_report']['save_db_settings'][] = $config_vars;
	}
}

if (! function_exists('prepareDBSettingContext')) {
	function prepareDBSettingContext(array $config_vars): void {
		$GLOBALS['smm_report']['prepare_db_setting_context'][] = $config_vars;
	}
}

if (! function_exists('loadGeneralSettingParameters')) {
	function loadGeneralSettingParameters(array &$subActions, string $defaultAction = ''): void {
		$GLOBALS['context']['sub_action'] = (string) ($_REQUEST['sa'] ?? $defaultAction);
	}
}

if (! function_exists('call_helper')) {
	function call_helper(mixed $callable): void {
		if (is_callable($callable)) {
			call_user_func($callable);
		}
	}
}

if (! function_exists('create_control_richedit')) {
	function create_control_richedit(array &$editorOptions): void {
		$GLOBALS['smm_report']['richedit'][] = $editorOptions;
	}
}

// The package helpers of the Builder: the real implementations from
// Subs-Package.php are replaced by their direct file system counterparts
if (! function_exists('mktree')) {
	function mktree($strPath, $mode): bool
	{
		if (is_dir($strPath)) {
			return is_writable($strPath);
		}

		if (! @mkdir($strPath, $mode, true) && ! is_dir($strPath)) {
			return false;
		}

		return true;
	}
}

if (! function_exists('deltree')) {
	function deltree($dir, $delete_dir = true): void
	{
		if (is_file($dir)) {
			@unlink($dir);

			return;
		}

		if (! is_dir($dir)) {
			return;
		}

		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ($items as $item) {
			$item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
		}

		if ($delete_dir) {
			@rmdir($dir);
		}
	}
}

if (! function_exists('package_get_contents')) {
	function package_get_contents($filename)
	{
		return @file_get_contents($filename);
	}
}

if (! function_exists('package_put_contents')) {
	function package_put_contents($filename, $data, $testing = false)
	{
		return @file_put_contents($filename, $data);
	}
}

if (! function_exists('package_flush_cache')) {
	function package_flush_cache($trash = false): void
	{
	}
}

// The session helpers of the Handler tests: the same "post" contract as in
// Security.php, but without the ban and the user agent checks
if (! function_exists('checkSession')) {
	function checkSession($type = 'post', $from_action = '', $is_fatal = true)
	{
		global $modSettings, $sc;

		$check = $_POST[$_SESSION['session_var']] ?? (empty($modSettings['strictSessionCheck']) && isset($_POST['sc']) ? $_POST['sc'] : null);

		if ($type === 'post' && $check !== $sc) {
			fatal_error('session_timeout');
		}

		return '';
	}
}

if (! function_exists('checkSubmitOnce')) {
	function checkSubmitOnce($action, $is_fatal = true)
	{
		$_SESSION['forms'] ??= [];

		// Register a form number and store it in the session stack
		if ($action == 'register') {
			$GLOBALS['context']['form_sequence_number'] = 0;

			while (
				empty($GLOBALS['context']['form_sequence_number'])
				|| in_array($GLOBALS['context']['form_sequence_number'], $_SESSION['forms'])
			) {
				$GLOBALS['context']['form_sequence_number'] = mt_rand(1, 16000000);
			}
		} elseif ($action == 'check') {
			if (! isset($_REQUEST['seqnum'])) {
				return true;
			}

			if (! in_array($_REQUEST['seqnum'], $_SESSION['forms'])) {
				$_SESSION['forms'][] = (int) $_REQUEST['seqnum'];

				return true;
			}

			if ($is_fatal) {
				fatal_lang_error('error_form_already_submitted', false);
			}

			return false;
		} elseif ($action == 'free' && isset($_REQUEST['seqnum']) && in_array($_REQUEST['seqnum'], $_SESSION['forms'])) {
			$_SESSION['forms'] = array_diff($_SESSION['forms'], [$_REQUEST['seqnum']]);
		}
	}
}

if (! function_exists('fatal_lang_error')) {
	function fatal_lang_error($error, $log = false): never {
		throw new RuntimeException((string) $error);
	}
}

if (! function_exists('fatal_error')) {
	function fatal_error($error, $log = 'general'): never {
		throw new RuntimeException((string) $error);
	}
}
