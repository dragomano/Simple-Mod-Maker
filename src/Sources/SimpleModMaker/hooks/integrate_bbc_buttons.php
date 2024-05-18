<?php

return [
	'params' => [
		'bbc_tags' => ['array', true],
		'editor_tag_map' => ['array', true],
	],
	'body' => [
		"\$bbc_tags[] = [",
		"\t'code' => 'button_code',",
		"\t'description' => 'button desc'",
		"];" . PHP_EOL,
		"//\$var_dump(\$editor_tag_map);",
	]
];
