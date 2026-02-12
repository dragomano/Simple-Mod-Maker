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

class CreditsHook extends AbstractHook
{
	protected function defineName(): string
	{
		return 'integrate_credits';
	}

	public function getParameters(): array
	{
		return [];
	}

	public function getBody(): array
	{
		$name   = $this->context['smm_skeleton']['name'];
		$author = $this->context['smm_skeleton']['author'];

		if ($this->context['smm_skeleton']['smf_target_version'] !== '3.0') {
			return [
				"global \$context;" . PHP_EOL,
				"\$context['copyrights']['mods'][] = '$name by $author &copy; " . date('Y') . "';",
			];
		} else {
			return [
				"Utils::\$context['copyrights']['mods'][] = '$name by $author &copy; " . date('Y') . "';",
			];
		}
	}
}
