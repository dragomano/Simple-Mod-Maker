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

class WhosOnlineHook extends AbstractHook
{
	protected function defineName(): string
	{
		return 'integrate_whos_online';
	}

	public function getParameters(): array
	{
		return [
			'actions' => ['array'],
		];
	}

	public function getReturnType(): string
	{
		return '?string';
	}

	public function getBody(): array
	{
		return $this->context['smm_skeleton']['smf_target_version'] !== '3.0' ? [
			"global \$txt, \$scripturl;" . PHP_EOL,
			"if (empty(\$actions['action']) || \$actions['action'] !== 'your_action')",
			"\treturn '';" . PHP_EOL,
			"\$result = sprintf(\$txt['your_mod_who_viewing_your_action'], \$scripturl . '?action=' . \$actions['action']);" . PHP_EOL,
			"if (isset(\$actions['subaction'])) {",
			"\t\$result = sprintf(\$txt['your_mod_who_viewing_your_subaction'], \$scripturl . '?action=' . \$actions['action'] . ';' . \$actions['subaction']);",
			"}" . PHP_EOL,
			"return \$result;",
		] : [
			"if (empty(\$actions['action']) || \$actions['action'] !== 'your_action')",
			"\treturn '';" . PHP_EOL,
			"\$result = Lang::getTxt('your_mod_who_viewing_your_action', [",
			"\tConfig::\$scripturl . '?action=' . \$actions['action']",
			"]);" . PHP_EOL,
			"if (isset(\$actions['subaction'])) {",
			"\t\$result = Lang::getTxt('your_mod_who_viewing_your_subaction', [",
			"\t\tConfig::\$scripturl . '?action=' . \$actions['action'] . ';' . \$actions['subaction']",
			"\t]);",
			"}" . PHP_EOL,
			"return \$result;",
		];
	}
}
