<?php

$class->addMethod('exampleMethod')
	->setBody("// This area is available at the following url: ?action=example_action");

return [
	'params' => [
		'actions' => ['array', true],
	],
	'body' => [
		"\$actions['example_action'] = [false, [\$this, 'exampleMethod']];",
	]
];
