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

class ModifyPostHook extends AbstractHook
{
	protected function defineName(): string
	{
		return 'integrate_modify_post';
	}

	public function getParameters(): array
	{
		return [
			'messages_columns'  => ['array', true],
			'update_parameters' => ['array', true],
			'msgOptions'        => ['array', true],
			'topicOptions'      => ['array', true],
			'posterOptions'     => ['array', true],
			'messageInts'       => ['array', true],
		];
	}

	public function getBody(): array
	{
		return [];
	}
}
