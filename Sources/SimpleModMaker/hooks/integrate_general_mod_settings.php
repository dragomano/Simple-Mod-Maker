<?php

return [
	'params' => [
		'config_vars' => ['array', true],
	],
	'body' => [
		"//if (isset(\$config_vars[0]))",
		"//\t\$config_vars[] = ['title', 'mod_title'];" . PHP_EOL,
		"//\$config_vars[] = ['title', 'option_name'];",
	]
];
