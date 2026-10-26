<?php declare(strict_types=1);

/**
 * @package Simple Mod Maker
 * @link https://github.com/dragomano/Simple-Mod-Maker
 * @author Bugo <bugo@dragomano.ru>
 * @copyright 2022-2026 Bugo
 * @license https://opensource.org/licenses/BSD-3-Clause BSD
 */

namespace Bugo\SimpleModMaker\Hooks;

class LoadIllegalGuestPermissionsHook extends AbstractHook
{
	protected function defineName(): string
	{
		return 'integrate_load_illegal_guest_permissions';
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
		return [
			"global \$context;" . PHP_EOL,
			"\$context['non_guest_permissions'][] = '{$this->snakeName}_example_permission';",
		];
	}

	private function getModernBody(): array
	{
		return [
			"Utils::\$context['non_guest_permissions'][] = '{$this->snakeName}_example_permission';",
		];
	}
}
