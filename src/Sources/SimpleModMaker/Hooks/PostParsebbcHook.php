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

class PostParsebbcHook extends AbstractHook
{
	protected function defineName(): string
	{
		return 'integrate_post_parsebbc';
	}

	public function getParameters(): array
	{
		return [
			'message'    => ['string', true],
			'smileys'    => ['boolean', true],
			'cache_id'   => ['string', true],
			'parse_tags' => ['array', true],
		];
	}

	public function getBody(): array
	{
		return [];
	}
}
