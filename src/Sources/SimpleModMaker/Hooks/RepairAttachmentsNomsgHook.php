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

class RepairAttachmentsNomsgHook extends AbstractHook
{
	protected function defineName(): string
	{
		return 'integrate_repair_attachments_nomsg';
	}

	public function getParameters(): array
	{
		return [
			'ignore_ids' => ['array', true],
			'step'       => ['int'],
			'next_step'  => ['int'],
		];
	}

	public function getBody(): array
	{
		return [];
	}
}
