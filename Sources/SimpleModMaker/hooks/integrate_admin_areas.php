<?php

$area = [];

if ($context['smm_skeleton']['settings_area'] === 2) {
	$area = [
		"\$admin_areas['config']['areas']['modsettings']['subsections']['{$snake_name}'] = array(\$txt['{$snake_name}_title']);"
	];
}

if ($context['smm_skeleton']['settings_area'] === 3) {
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
}

return [
	'params' => [
		'admin_areas' => ['array', true],
	],
	'body' => array_merge([
		"global \$txt;" . PHP_EOL,
		"loadLanguage('{$classname}" . (empty($context['smm_skeleton']['use_lang_dir']) ? '' : '/') . "');" . PHP_EOL,
	], $area),
];
