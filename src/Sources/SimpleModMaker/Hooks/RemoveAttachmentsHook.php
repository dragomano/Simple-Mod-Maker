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

class RemoveAttachmentsHook extends AbstractHook
{
	protected function defineName(): string
	{
		return 'integrate_remove_attachments';
	}

	public function getParameters(): array
	{
		return [
			'attach' => ['array'],
		];
	}

	public function getBody(): array
	{
		return [];
	}
}
