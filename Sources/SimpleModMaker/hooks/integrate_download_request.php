<?php

return [
	'params' => [
		'attachRequest' => ['array', true],
	],
	'body' => [
		"global \$smcFunc;" . PHP_EOL,
		"if ((!empty(\$attachRequest) && is_resource(\$attachRequest)) || empty(\$_REQUEST['item']))",
		"\treturn;" . PHP_EOL,
		"\$attachRequest = \$smcFunc['db_query']('', 'Here is your SQL',",
		"\tarray(",
		"\t\t'attach' => (int) \$_REQUEST['attach'],",
		"\t\t'item'   => (int) \$_REQUEST['item'],",
		"\t)",
		");"
	],
];
