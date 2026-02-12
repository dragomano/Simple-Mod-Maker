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

class SsiTopBoardsHook extends AbstractHook
{
	protected function defineName(): string
	{
		return 'integrate_ssi_topBoards';
	}

	public function getParameters(): array
	{
		return [
			'boards' => ['array', true],
		];
	}

	public function getBody(): array
	{
		return [
			"echo '<pre>'. print_r(\$boards, true) . '</pre>';",
		];
	}
}
