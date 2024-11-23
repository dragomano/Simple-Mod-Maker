<?php

$type = "\$alert_types['{$snake_name}']['some_action'] = [
	'alert' => 'yes', // or 'never'
	'email' => 'yes', // or 'never'
	'permission' => [
		'name'     => 'some_permission',
		'is_board' => false,
	]
];";

return [
	'params' => [
		'alert_types' => ['array', true],
		'group_options' => ['array', true],
	],
	'body' => $context['smm_skeleton']['smf_target_version'] !== '3.0' ? [
		"global \$txt;" . PHP_EOL,
		"\$txt['alert_group_{$snake_name}'] = \$txt['{$snake_name}_title'];" . PHP_EOL,
		$type
	] : [
		"Lang::\$txt['alert_group_{$snake_name}'] = Lang::\$txt['{$snake_name}_title'];" . PHP_EOL,
		$type
	],
];
