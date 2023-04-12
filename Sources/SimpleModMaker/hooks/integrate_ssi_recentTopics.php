<?php

return [
	'params' => [
		'posts' => ['array', true],
	],
	'body' => [
		"echo '<pre>'. print_r(\$posts, true) . '</pre>';",
	]
];
