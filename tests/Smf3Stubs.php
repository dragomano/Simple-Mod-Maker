<?php declare(strict_types=1);

// Minimal stubs of the SMF 3.0 API entry points, so the generated installers
// and handlers can be executed outside a real SMF 3.0 forum. Every recording
// stub accumulates its calls in the static $calls array.

namespace SMF\Db {
	if (! class_exists(DatabaseApi::class)) {
		class DatabaseApi
		{
			public static object $db;
		}
	}
}

namespace SMF {
	if (! class_exists(IntegrationHook::class)) {
		class IntegrationHook
		{
			/** @var list<array{0: string, 1: string}> */
			public static array $calls = [];

			public static function add(string $hook, string $callable): void
			{
				self::$calls[] = [$hook, $callable];
			}
		}
	}

	if (! class_exists(Config::class)) {
		class Config
		{
			public static array $modSettings = [];

			public static string $scripturl = 'https://smf3.test/index.php';

			/** @var list<array> */
			public static array $calls = [];

			public static function updateModSettings(array $settings): void
			{
				self::$calls[] = ['updateModSettings', $settings];
			}
		}
	}

	if (! class_exists(Lang::class)) {
		class Lang
		{
			public static array $txt = [];

			/** @var list<array> */
			public static array $calls = [];

			public static function load(string $name): void
			{
				self::$calls[] = ['load', $name];
			}
		}
	}

	if (! class_exists(Utils::class)) {
		class Utils
		{
			public static array $context = [];

			/** @var list<array> */
			public static array $calls = [];

			public static function redirectexit(string $url = ''): void
			{
				self::$calls[] = ['redirectexit', $url];
			}
		}
	}

	if (! class_exists(Menu::class)) {
		class Menu
		{
			public static array $loaded = [];
		}
	}

	if (! class_exists(Theme::class)) {
		class Theme
		{
			/** @var list<array> */
			public static array $calls = [];

			public static function loadTemplate(string $name): void
			{
				self::$calls[] = ['loadTemplate', $name];
			}
		}
	}

	if (! class_exists(User::class)) {
		class User
		{
			public static ?object $me = null;
		}
	}
}

namespace SMF\Actions\Admin {
	if (! class_exists(ACP::class)) {
		class ACP
		{
			/** @var list<array> */
			public static array $calls = [];

			public static function saveDBSettings(array $configVars): void
			{
				self::$calls[] = ['saveDBSettings', $configVars];
			}

			public static function prepareDBSettingContext(array $configVars): void
			{
				self::$calls[] = ['prepareDBSettingContext', $configVars];
			}
		}
	}
}
