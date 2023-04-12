<?php

return [
	'params' => [
		'return' => ['array', true],
		'eventOptions' => ['array'],
	],
	'body' => [
		"echo '<pre>'. print_r(\$return, true) . '</pre>';",
	]
];
