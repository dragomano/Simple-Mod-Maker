<?php

return [
	'params' => [
		'boards' => ['array', true],
	],
	'body' => [
		"echo '<pre>'. print_r(\$boards, true) . '</pre>';",
	]
];
