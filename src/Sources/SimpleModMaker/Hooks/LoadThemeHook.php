<?php declare(strict_types=1);

/**
 * @package Simple Mod Maker
 * @link https://github.com/dragomano/Simple-Mod-Maker
 * @author Bugo <bugo@dragomano.ru>
 * @copyright 2022-2026 Bugo
 * @license https://opensource.org/licenses/BSD-3-Clause BSD
 *
 * @version 1.0
 */

namespace Bugo\SimpleModMaker\Hooks;

class LoadThemeHook extends AbstractHook
{
	protected function defineName(): string
	{
		return 'integrate_load_theme';
	}

	public function getParameters(): array
	{
		return [];
	}

	public function getBody(): array
	{
		return [
			"// loadCSSFile('https://site.com/style.css', ['external' => true]);" . PHP_EOL,
			"// loadJavaScriptFile('https://site.com/script.js', ['external' => true]);" . PHP_EOL,
			"// addInlineJavaScript('some JS code', true);" . PHP_EOL,
			"// loadLanguage('YourLanguageFile');",
		];
	}
}
