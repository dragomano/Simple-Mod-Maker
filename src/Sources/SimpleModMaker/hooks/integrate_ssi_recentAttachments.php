<?php

return [
	'params' => [
		'attachments' => ['array', true],
	],
	'body' => [
		"echo '<pre>'. print_r(\$attachments, true) . '</pre>';",
	]
];
