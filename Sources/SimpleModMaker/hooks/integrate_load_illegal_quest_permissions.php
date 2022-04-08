<?php

return [
	'params' => [],
	'body' => [
		"global \$context;" . PHP_EOL,
		"\$context['non_guest_permissions'][] = '{$snake_name}_example_permission';",
	]
];
