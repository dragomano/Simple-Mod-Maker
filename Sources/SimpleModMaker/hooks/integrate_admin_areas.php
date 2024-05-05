<?php

$area = [];

if ($context['smm_skeleton']['settings_area'] === 2) {
	if ($context['smm_skeleton']['smf_target_version'] !== '3.0') {
		$area = [
			"\$admin_areas['config']['areas']['modsettings']['subsections']['{$snake_name}'] = [\$txt['{$snake_name}_title']];"
		];
	} else {
		$area = [
			"\$admin_areas['config']['areas']['modsettings']['subsections']['{$snake_name}'] = [Lang::\$txt['{$snake_name}_title']];"
		];
	}
}

if ($context['smm_skeleton']['settings_area'] === 3) {
	if ($context['smm_skeleton']['smf_target_version'] !== '3.0') {
		$area = [
			"\$admin_areas['config']['areas']['{$snake_name}'] = [
	'label'       => \$txt['{$snake_name}_title'],
	'function'    => [\$this, 'settings'],
	// .main_icons {$snake_name}
	'icon'        => '{$snake_name}',
	'subsections' => [
		'section1' => [\$txt['{$snake_name}_section1_title']],
		//'section2' => [\$txt['{$snake_name}_section2_title']],
	]
];"
		];
	} else {
		$area = [
			"\$admin_areas['config']['areas']['{$snake_name}'] = [
	'label'       => Lang::\$txt['{$snake_name}_title'],
	'function'    => [\$this, 'settings'],
	// .main_icons {$snake_name}
	'icon'        => '{$snake_name}',
	'subsections' => [
		'section1' => [Lang::\$txt['{$snake_name}_section1_title']],
		//'section2' => [Lang::\$txt['{$snake_name}_section2_title']],
	]
];"
		];
	}
}

return [
	'params' => [
		'admin_areas' => ['array', true],
	],
	'body' => $context['smm_skeleton']['smf_target_version'] !== '3.0' ? array_merge([
		"global \$txt;" . PHP_EOL,
		"loadLanguage('{$classname}" . (empty($context['smm_skeleton']['use_lang_dir']) ? '' : '/') . "');" . PHP_EOL,
	], $area) : array_merge([
		"Lang::load('{$classname}" . (empty($context['smm_skeleton']['use_lang_dir']) ? '' : '/') . "');" . PHP_EOL,
	], $area),
];
