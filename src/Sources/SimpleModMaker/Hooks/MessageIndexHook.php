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

class MessageIndexHook extends AbstractHook
{
	protected function defineName(): string
	{
		return 'integrate_message_index';
	}

	public function getParameters(): array
	{
		return [
			'message_index_selects'      => ['array', true],
			'message_index_tables'       => ['array', true],
			'message_index_parameters'   => ['array', true],
			'message_index_wheres'       => ['array', true],
			'topic_ids'                  => ['array', true],
			'message_index_topic_wheres' => ['array', true],
		];
	}

	public function getBody(): array
	{
		return [
			"// Add your message index modifications here",
			"// You can modify database queries for message index",
		];
	}
}
