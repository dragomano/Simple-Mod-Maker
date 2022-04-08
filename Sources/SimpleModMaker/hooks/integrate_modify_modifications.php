<?php

return [
	'params' => [
		'subActions' => ['array', true],
	],
	'body' => [
		"\$subActions['{$snake_name}'] = array(\$this, 'settings');",
	]
];
