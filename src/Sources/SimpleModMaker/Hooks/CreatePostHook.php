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

class CreatePostHook extends AbstractHook
{
	protected function defineName(): string
	{
		return 'integrate_create_post';
	}

	public function getParameters(): array
	{
		return [
			'msgOptions'         => ['array', true],
			'topicOptions'       => ['array', true],
			'posterOptions'      => ['array', true],
			'message_columns'    => ['array', true],
			'message_parameters' => ['array', true],
		];
	}

	public function getBody(): array
	{
		return [
			"// Add your code for post creation here",
			"// You can modify \$msgOptions, \$topicOptions, \$posterOptions",
			"// Add columns to \$message_columns array",
			"// Add parameters to \$message_parameters array",
		];
	}
}
