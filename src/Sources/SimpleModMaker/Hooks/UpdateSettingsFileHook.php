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

class UpdateSettingsFileHook extends AbstractHook
{
	protected function defineName(): string
	{
		return 'integrate_update_settings_file';
	}

	public function getParameters(): array
	{
		return [
			'settings_defs' => ['array', true],
		];
	}

	public function getBody(): array
	{
		return [
			"\$settings_defs['your_variable'] = [",
			"\t'text' => implode(\"\\n\", [",
			"\t\t'/**',",
			"\t\t' * Description',",
			"\t\t' *',",
			"\t\t' * @var string',",
			"\t\t' */',",
			"\t]),",
			"\t'default' => 'default_value',",
			"\t'type' => 'string'",
			"];",
		];
	}
}
