<?php

return [
	'params' => [
		'attachRequest' => ['array', true],
	],
	'body' => $context['smm_skeleton']['smf_target_version'] !== '3.0' ? [
		"global \$smcFunc;" . PHP_EOL,
		"if ((!empty(\$attachRequest) && is_resource(\$attachRequest)) || empty(\$_REQUEST['item']))",
		"\treturn;" . PHP_EOL,
		"\$attachRequest = \$smcFunc['db_query']('', 'Here is your SQL',",
		"\t[",
		"\t\t'attach' => (int) \$_REQUEST['attach'],",
		"\t\t'item'   => (int) \$_REQUEST['item'],",
		"\t]",
		");"
	] : [
		"if ((!empty(\$attachRequest) && is_resource(\$attachRequest)) || empty(\$_REQUEST['item']))",
		"\treturn;" . PHP_EOL,
		"\$attachRequest = Db::\$db->query('', 'Here is your SQL',",
		"\t[",
		"\t\t'attach' => (int) \$_REQUEST['attach'],",
		"\t\t'item'   => (int) \$_REQUEST['item'],",
		"\t]",
		");"
	],
];
