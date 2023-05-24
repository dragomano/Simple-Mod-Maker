<?php

return [
	'params' => [
		'settings_defs' => ['array', true],
	],
	'body' => [
		"\$settings_defs['your_variable'] = [",
		"\t'text' => implode(\"\\n\", [",
		"\t\t'/**',",
		"\t\t' * Description',",
		"\t\t' *',",
		"\t\t' * @var string',",
		"\t\t' */',",
		"\t]),",
		"\t'default' => 'default_value',",
		"\t'type' => 'string'",
		"];",
	]
];
