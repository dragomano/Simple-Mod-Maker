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

class MenuButtonsHook extends AbstractHook
{
	protected function defineName(): string
	{
		return 'integrate_menu_buttons';
	}

	public function getParameters(): array
	{
		return [
			'buttons' => ['array', true],
		];
	}

	public function getBody(): array
	{
		return [
			"// Add menu buttons here",
			"// \$buttons['your_button'] = [",
			"//     'title' => 'Your Button',",
			"//     'href' => \$scripturl . '?action=your_action',",
			"//     'show' => true,",
			"// ];",
		];
	}
}
