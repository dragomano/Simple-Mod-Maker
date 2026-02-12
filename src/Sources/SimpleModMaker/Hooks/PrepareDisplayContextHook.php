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

class PrepareDisplayContextHook extends AbstractHook
{
	protected function defineName(): string
	{
		return 'integrate_prepare_display_context';
	}

	public function getParameters(): array
	{
		return [
			'output'  => ['array', true],
			'message' => ['array', true],
			'counter' => ['int'],
		];
	}

	public function getBody(): array
	{
		return [];
	}
}
