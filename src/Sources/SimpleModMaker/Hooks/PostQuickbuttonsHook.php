<?php declare(strict_types=1);

/**
 * @package Simple Mod Maker
 * @link https://github.com/dragomano/Simple-Mod-Maker
 * @author Bugo <bugo@dragomano.ru>
 * @copyright 2022-2025 Bugo
 * @license https://opensource.org/licenses/BSD-3-Clause BSD
 *
 * @version 0.9
 */

namespace Bugo\SimpleModMaker\Hooks;

class PostQuickbuttonsHook extends AbstractHook
{
	protected function defineName(): string
	{
		return 'integrate_post_quickbuttons';
	}

	public function getParameters(): array
	{
		return [
			'list_items' => ['array', true],
		];
	}

	public function getBody(): array
	{
		return [
			"/*",
			"\$list_items['my_button'] = [",
			"\t'label' => 'Label text',",
			"\t'href'  => 'https://site.domain',",
			"\t'icon'  => 'home',",
			"\t'show'  => true",
			"];",
			"*/",
		];
	}
}
