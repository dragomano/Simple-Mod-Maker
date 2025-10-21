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

class DisplayTopicHook extends AbstractHook
{
	protected function defineName(): string
	{
		return 'integrate_display_topic';
	}

	public function getParameters(): array
	{
		return [
			'topic_selects'    => ['array', true],
			'topic_tables'     => ['array', true],
			'topic_parameters' => ['array', true],
		];
	}

	public function getBody(): array
	{
		return [
			"// Add your topic display modifications here",
			"// You can modify database queries for topic display",
		];
	}
}
