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

class AlertTypesHook extends AbstractHook
{
	protected function defineName(): string
	{
		return 'integrate_alert_types';
	}

	public function getParameters(): array
	{
		return [
			'alert_types'   => ['array', true],
			'group_options' => ['array', true],
		];
	}

	public function getBody(): array
	{
		$alertTypeCode = "\$alert_types['$this->snakeName']['some_action'] = [
	'alert' => 'yes', // or 'never'
	'email' => 'yes', // or 'never'
	'permission' => [
		'name'     => 'some_permission',
		'is_board' => false,
	]
];";

		if ($this->context['smm_skeleton']['smf_target_version'] !== '3.0') {
			return [
				"global \$txt;" . PHP_EOL,
				"\$txt['alert_group_$this->snakeName'] = \$txt['{$this->snakeName}_title'];" . PHP_EOL,
				$alertTypeCode
			];
		} else {
			return [
				"Lang::\$txt['alert_group_$this->snakeName'] = Lang::\$txt['{$this->snakeName}_title'];" . PHP_EOL,
				$alertTypeCode
			];
		}
	}
}
