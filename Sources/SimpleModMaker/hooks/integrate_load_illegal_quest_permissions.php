<?php

return [
	'params' => [],
	'body' => $context['smm_skeleton']['smf_target_version'] !== '3.0' ? [
		"global \$context;" . PHP_EOL,
		"\$context['non_guest_permissions'][] = '{$snake_name}_example_permission';",
	] : [
		"Utils::\$context['non_guest_permissions'][] = '{$snake_name}_example_permission';",
	]
];
