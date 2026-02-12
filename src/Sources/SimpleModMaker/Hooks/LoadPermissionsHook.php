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

class LoadPermissionsHook extends AbstractHook
{
	protected function defineName(): string
	{
		return 'integrate_load_permissions';
	}

	public function getParameters(): array
	{
		return [
			'permissionGroups'     => ['array', true],
			'permissionList'       => ['array', true],
			'leftPermissionGroups' => ['array', true],
			'hiddenPermissions'    => ['array', true],
			'relabelPermissions'   => ['array', true],
		];
	}

	public function getBody(): array
	{
		return [
			"\$permissionGroups['membergroup']['simple'] = ['$this->snakeName'];",
			"\$permissionGroups['membergroup']['classic'] = ['$this->snakeName'];" . PHP_EOL,
			"\$permissionList['membergroup']['example_permission'] = [false, '$this->snakeName', '$this->snakeName'];",
		];
	}
}
