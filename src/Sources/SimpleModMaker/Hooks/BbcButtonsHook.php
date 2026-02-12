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

class BbcButtonsHook extends AbstractHook
{
	protected function defineName(): string
	{
		return 'integrate_bbc_buttons';
	}

	public function getParameters(): array
	{
		return [
			'buttons'        => ['array', true],
			'editor_tag_map' => ['array', true],
		];
	}

	public function getBody(): array
	{
		return [
			"\$buttons[] = [",
			"\t'code' => 'button_code',",
			"\t'description' => 'button desc'",
			"];" . PHP_EOL,
			"//\$var_dump(\$editor_tag_map);",
		];
	}
}
