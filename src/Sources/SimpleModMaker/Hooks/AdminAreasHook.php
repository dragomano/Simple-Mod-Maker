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

class AdminAreasHook extends AbstractHook
{
	protected function defineName(): string
	{
		return 'integrate_admin_areas';
	}

	public function getParameters(): array
	{
		return [
			'admin_areas' => ['array', true],
		];
	}

	public function getBody(): array
	{
		$area = [];

		if ($this->context['smm_skeleton']['settings_area'] === 2) {
			if ($this->context['smm_skeleton']['smf_target_version'] !== '3.0') {
				$area = [
					"\$admin_areas['config']['areas']['modsettings']['subsections']['$this->snakeName'] = [\$txt['{$this->snakeName}_title']];"
				];
			} else {
				$area = [
					"\$admin_areas['config']['areas']['modsettings']['subsections']['$this->snakeName'] = [Lang::\$txt['{$this->snakeName}_title']];"
				];
			}
		}

		if ($this->context['smm_skeleton']['settings_area'] === 3) {
			if ($this->context['smm_skeleton']['smf_target_version'] !== '3.0') {
				$area = [
					<<<PHP
\$admin_areas['config']['areas']['$this->snakeName'] = [
	'label'       => \$txt['{$this->snakeName}_title'],
	'function'    => [\$this, 'settings'],
	// .main_icons {$this->snakeName}
	'icon'        => '$this->snakeName',
	'subsections' => [
		'section1' => [\$txt['{$this->snakeName}_section1_title']],
		//'section2' => [\$txt['{$this->snakeName}_section2_title']],
	]
];
PHP
				];
			} else {
				$area = [
					<<<PHP
\$admin_areas['config']['areas']['$this->snakeName'] = [
	'label'       => Lang::\$txt['{$this->snakeName}_title'],
	'function'    => [\$this, 'settings'],
	// .main_icons {$this->snakeName}
	'icon'        => '$this->snakeName',
	'subsections' => [
		'section1' => [Lang::\$txt['{$this->snakeName}_section1_title']],
		//'section2' => [Lang::\$txt['{$this->snakeName}_section2_title']],
	]
];
PHP
				];
			}
		}

		return $this->context['smm_skeleton']['smf_target_version'] !== '3.0'
			? array_merge([
				"global \$txt;" . PHP_EOL,
				"loadLanguage('$this->classname" . (empty($this->context['smm_skeleton']['use_lang_dir']) ? '' : '/') . "');" . PHP_EOL,
			], $area)
			: array_merge([
				"Lang::load('$this->classname" . (empty($this->context['smm_skeleton']['use_lang_dir']) ? '' : '/') . "');" . PHP_EOL,
			], $area);
	}
}
