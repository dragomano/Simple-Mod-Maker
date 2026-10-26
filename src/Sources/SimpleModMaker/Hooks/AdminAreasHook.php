<?php declare(strict_types=1);

/**
 * @package Simple Mod Maker
 * @link https://github.com/dragomano/Simple-Mod-Maker
 * @author Bugo <bugo@dragomano.ru>
 * @copyright 2022-2026 Bugo
 * @license https://opensource.org/licenses/BSD-3-Clause BSD
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
		$area = [];

		if ($this->context['smm_skeleton']['settings_area'] === 2) {
			$area = [
				"\$admin_areas['config']['areas']['modsettings']['subsections']['$this->snakeName'] = [\$txt['{$this->snakeName}_title']];"
			];
		}

		if ($this->context['smm_skeleton']['settings_area'] === 3) {
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
		}

		return array_merge([
			"global \$txt;" . PHP_EOL,
			"loadLanguage('$this->classname" . (empty($this->context['smm_skeleton']['use_lang_dir']) ? '' : '/') . "');" . PHP_EOL,
		], $area);
	}

	private function getModernBody(): array
	{
		$area = [];

		if ($this->context['smm_skeleton']['settings_area'] === 2) {
			$area = [
				"\$admin_areas['config']['areas']['modsettings']['subsections']['$this->snakeName'] = [Lang::\$txt['{$this->snakeName}_title']];"
			];
		}

		if ($this->context['smm_skeleton']['settings_area'] === 3) {
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

		return array_merge([
			"Lang::load('$this->classname" . (empty($this->context['smm_skeleton']['use_lang_dir']) ? '' : '/') . "');" . PHP_EOL,
		], $area);
	}
}
