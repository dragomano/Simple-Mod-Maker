<?php

if (empty($context['smm_skeleton']['make_dir']))
	return [];

return [
	'params' => [
		'classMap' => ['array', true],
	],
	'body' => [
		"\$classMap['{$context['smm_skeleton']['author']}\\\\{$classname}\\\\'] = '{$classname}/';",
	]
];
