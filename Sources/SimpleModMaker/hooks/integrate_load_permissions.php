<?php

return [
	'params' => [
		'permissionGroups' => ['array', true],
		'permissionList' => ['array', true],
		//'leftPermissionGroups' => ['array', true],
		//'hiddenPermissions' => ['array', true],
		//'relabelPermissions' => ['array', true],
	],
	'body' => [
		"\$permissionGroups['membergroup']['simple'] = ['{$snake_name}'];",
		"\$permissionGroups['membergroup']['classic'] = ['{$snake_name}'];" . PHP_EOL,
		"\$permissionList['membergroup']['example_permission']  = [false, '{$snake_name}', '{$snake_name}'];",
	]
];
