<?php

return [
	'params' => [
		'language_files' => ['array', true],
		'include_files' => ['array', true],
		'settings_search' => ['array', true],
	],
	'body' => [
		"\$language_files[] = '{$classname}';",
		"\$settings_search[] = [[\$this, 'settings'], 'area=modsettings;sa={$snake_name}'];",
	]
];
