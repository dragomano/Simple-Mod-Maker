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

class SsiQueryMembersHook extends AbstractHook
{
	protected function defineName(): string
	{
		return 'integrate_ssi_queryMembers';
	}

	public function getParameters(): array
	{
		return [
			'members' => ['array', true],
		];
	}

	public function getBody(): array
	{
		return [
			"echo '<pre>'. print_r(\$members, true) . '</pre>';",
		];
	}
}
