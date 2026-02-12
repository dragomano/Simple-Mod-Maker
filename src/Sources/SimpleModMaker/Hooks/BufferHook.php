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

class BufferHook extends AbstractHook
{
	protected function defineName(): string
	{
		return 'integrate_buffer';
	}

	public function getParameters(): array
	{
		return [
			'buffers' => ['string'],
		];
	}

	public function getReturnType(): string
	{
		return 'string';
	}

	public function getBody(): array
	{
		return [
			"if (isset(\$_REQUEST['xml']))",
			"\treturn \$buffers;" . PHP_EOL,
			"// return str_replace('h1>', 'h1 class=\"title_class\">', \$buffers);",
		];
	}
}
