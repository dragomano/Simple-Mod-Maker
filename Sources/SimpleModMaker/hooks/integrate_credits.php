<?php

return [
	'params' => [],
	'body' => $context['smm_skeleton']['smf_target_version'] !== '3.0' ? [
		"global \$context;" . PHP_EOL,
		"\$context['copyrights']['mods'][] = '{$context['smm_skeleton']['name']} by {$context['smm_skeleton']['author']} &copy; " . date('Y') . "';",
	] : [
		"Utils::\$context['copyrights']['mods'][] = '{$context['smm_skeleton']['name']} by {$context['smm_skeleton']['author']} &copy; " . date('Y') . "';",
	]
];
