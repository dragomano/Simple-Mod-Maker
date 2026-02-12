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

class AdminSearchHook extends AbstractHook
{
	protected function defineName(): string
	{
		return 'integrate_admin_search';
	}

	public function getParameters(): array
	{
		return [
			'language_files'  => ['array', true],
			'include_files'   => ['array', true],
			'settings_search' => ['array', true],
		];
	}

	public function getBody(): array
	{
		return [
			"\$language_files[] = '$this->classname';",
			"\$settings_search[] = [[\$this, 'settings'], 'area=modsettings;sa=$this->snakeName'];",
		];
	}
}
