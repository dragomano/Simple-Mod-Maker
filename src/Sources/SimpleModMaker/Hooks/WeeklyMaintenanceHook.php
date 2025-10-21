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

class WeeklyMaintenanceHook extends AbstractHook
{
	protected function defineName(): string
	{
		return 'integrate_weekly_maintenance';
	}

	public function getParameters(): array
	{
		return [];
	}

	public function getBody(): array
	{
		$tasks = '';

		foreach ($this->context['smm_skeleton']['legacy_tasks'] ?? [] as $task) {
			if (empty($task['regularity'])) {
				continue;
			}

			$tasks .= "\$this->{$task['method']};";
		}

		return [
			$tasks,
		];
	}
}
