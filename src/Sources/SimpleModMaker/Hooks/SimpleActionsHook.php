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

class SimpleActionsHook extends AbstractHook
{
	protected function defineName(): string
	{
		return 'integrate_simple_actions';
	}

	public function getParameters(): array
	{
		return [
			'simpleActions'    => ['array', true],
			'simpleAreas'      => ['array', true],
			'simpleSubActions' => ['array', true],
			'extraParams'      => ['array', true],
			'xmlActions'       => ['array', true],
		];
	}

	public function getBody(): array
	{
		return [
			"// \$simpleActions[] = 'example_simple_action';",
			"// \$xmlActions[] = 'example_xml_action';",
		];
	}
}
