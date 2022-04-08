<?php

return [
	'params' => [],
	'body' => [
		"global \$context;" . PHP_EOL,
		"\$context['copyrights']['mods'][] = '{$context['smm_skeleton']['name']} by {$context['smm_skeleton']['author']} &copy; " . date('Y') . "';",
	]
];
