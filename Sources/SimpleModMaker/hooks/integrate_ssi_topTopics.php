<?php

return [
	'params' => [
		'topics' => ['array', true],
		'type' => ['string'],
	],
	'body' => [
		"echo '<pre>'. print_r(\$topics, true) . '</pre>';",
	]
];
