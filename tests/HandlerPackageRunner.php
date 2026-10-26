<?php declare(strict_types=1);

use Bugo\SimpleModMaker\Handler;

// Runs the whole Handler::generator() flow in a separate PHP process: the
// successful package download prints the ZIP archive to STDOUT and exits,
// so the side effects (remembered settings, package working folder) are
// reported through a file written by a shutdown handler. The recording
// stubs come from the shared Bootstrap.

if (! defined('SMF')) {
	define('SMF', true);
}

if (! defined('SMM_NAME')) {
	define('SMM_NAME', 'Simple Mod Maker');
}

if (! defined('SMF_VERSION')) {
	define('SMF_VERSION', '3.0.0');
}

if (! defined('SMM_FILENAME_PATTERN')) {
	define('SMM_FILENAME_PATTERN', '^(?:Class-)?[A-Z][a-zA-Z]+$');
}

if (! defined('SMM_MODNAME_DEFAULT')) {
	define('SMM_MODNAME_DEFAULT', 'My New Mod');
}

if (! defined('SMM_VENDOR_DEFAULT')) {
	define('SMM_VENDOR_DEFAULT', 'MyMods');
}

require_once __DIR__ . '/Bootstrap.php';

class HandlerPackageRunner
{
	public static function run(array $params): void
	{
		register_shutdown_function(function () use ($params): void {
			file_put_contents($params['report_file'], json_encode([
				'settings_updates' => $GLOBALS['smm_settings_updates'] ?? [],
				'error'            => $GLOBALS['smm_runner_error'] ?? null,
				'packages'         => glob($params['packages_dir'] . '/*', GLOB_ONLYDIR) ?: [],
			]));
		});

		$GLOBALS['txt']         = $params['txt'];
		$GLOBALS['scripturl']   = 'https://smf.test/index.php';
		$GLOBALS['modSettings'] = $params['mod_settings'];
		$GLOBALS['packagesdir'] = $params['packages_dir'];
		$GLOBALS['sc']          = $params['sc'];

		$GLOBALS['context'] = array_merge($GLOBALS['context'] ?? [], [
			'post_errors'     => [],
			'admin_menu_name' => 'admin_menu',
			'admin_menu'      => [],
			'smm_languages'   => $params['languages'],
		]);

		$_SESSION                = ['session_var' => 'sc_session_var'];
		$_POST                   = $params['post'];
		$_POST['sc_session_var'] = $params['sc'];

		// The web context always has an output buffer for the download()
		// method to clean, and its absence would turn into a notice here
		ob_start();

		try {
			(new Handler())->generator();

			// Only a submission that skipped the generation reaches this line
			$GLOBALS['smm_runner_error'] = 'The generator returned without building a package.';
		} catch (Throwable $e) {
			$GLOBALS['smm_runner_error'] = $e->getMessage() . "\n" . $e->getTraceAsString();
		}
	}
}
