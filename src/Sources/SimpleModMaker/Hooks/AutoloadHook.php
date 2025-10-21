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

class AutoloadHook extends AbstractHook
{
	protected function defineName(): string
	{
		return 'integrate_autoload';
	}

	public function getParameters(): array
	{
		return [
			'classMap' => ['array', true],
		];
	}

	public function getBody(): array
	{
		if (empty($this->context['smm_skeleton']['make_dir'])) {
			return [
				"// \$classMap['YourNamespace\\\\YourClass\\\\'] = 'YourClass/';",
			];
		}

		$author = $this->context['smm_skeleton']['author'];

		return [
			"\$classMap['$author\\\\$this->classname\\\\'] = '$this->classname/';",
		];
	}
}
