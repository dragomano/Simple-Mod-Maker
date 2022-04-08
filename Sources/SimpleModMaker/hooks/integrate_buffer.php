<?php

return [
	'params' => [
		'buffer' => ['string'],
	],
	'return' => 'string',
	'body' => [
		"if (isset(\$_REQUEST['xml']))",
		"\treturn \$buffer;" . PHP_EOL,
		"// return str_replace('h1>', 'h1 class=\"title_class\">', \$buffer);",
	]
];
