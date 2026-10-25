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
		return $this->isModernTarget() ? $this->getModernBody() : $this->getLegacyBody();
	}

	public function getDualBody(): ?array
	{
		return [
			'legacy' => $this->getLegacyBody(),
			'modern' => $this->getModernBody(),
		];
	}

	private function getLegacyBody(): array
	{
		$name   = $this->context['smm_skeleton']['name'];
		$author = $this->context['smm_skeleton']['author'];

		$copyright = var_export("$name by $author &copy; " . date('Y'), true);

		return [
			"global \$context;" . PHP_EOL,
			"\$context['copyrights']['mods'][] = $copyright;",
		];
	}

	private function getModernBody(): array
	{
		$name   = $this->context['smm_skeleton']['name'];
		$author = $this->context['smm_skeleton']['author'];

		$copyright = var_export("$name by $author &copy; " . date('Y'), true);

		return [
			"Utils::\$context['copyrights']['mods'][] = $copyright;",
		];
	}
}
