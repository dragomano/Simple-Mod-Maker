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

class DailyMaintenanceHook extends AbstractHook
{
	private array $legacyTasks;

	public function __construct(array $context, string $classname, string $snakeName)
	{
		parent::__construct($context, $classname, $snakeName);

		$this->legacyTasks = $context['smm_skeleton']['legacy_tasks'] ?? [];
	}

	protected function defineName(): string
	{
		return 'integrate_daily_maintenance';
	}

	public function getParameters(): array
	{
		return [];
	}

	public function getBody(): array
	{
		if (empty($this->legacyTasks)) {
			return [
				"// Add your daily maintenance tasks here",
				"// \$this->yourDailyMethod();",
			];
		}

		$body = [];
		foreach ($this->legacyTasks as $task) {
			if (empty($task['regularity'])) {
				$body[] = "\$this->{$task['method']};";
			}
		}

		return $body;
	}
}
