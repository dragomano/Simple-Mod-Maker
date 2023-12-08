<?php

return [
	'params' => [
		'config_vars' => ['array', true],
	],
	'body' => [
		"//if (isset(\$config_vars[0]))",
		"//\t\$config_vars[] = array('title', 'mod_title');" . PHP_EOL,
		"//\$config_vars[] = array('title', 'option_name');",
	]
];
