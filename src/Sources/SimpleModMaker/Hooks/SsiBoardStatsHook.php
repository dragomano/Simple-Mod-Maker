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

class SsiBoardStatsHook extends AbstractHook
{
	protected function defineName(): string
	{
		return 'integrate_ssi_boardStats';
	}

	public function getParameters(): array
	{
		return [
			'totals' => ['array', true],
		];
	}

	public function getBody(): array
	{
		return [
			"echo '<pre>'. print_r(\$totals, true) . '</pre>';",
		];
	}
}
