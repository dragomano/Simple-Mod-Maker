<?php

return [
	'params' => [
		'return' => ['array', true],
		'topPollInstead' => ['bool'],
	],
	'body' => [
		"echo '<pre>'. print_r(\$return, true) . '</pre>';",
	]
];
