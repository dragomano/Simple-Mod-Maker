<?php

return [
	'params' => [
		'admin_areas' => ['array', true],
	],
	'body' => [
		"global \$txt;" . PHP_EOL,
		"loadLanguage('{$classname}" . (empty($context['smm_skeleton']['use_lang_dir']) ? '' : '/') . "');" . PHP_EOL,
		"\$admin_areas['config']['areas']['modsettings']['subsections']['{$snake_name}'] = array(\$txt['{$snake_name}_title']);",
	]
];
