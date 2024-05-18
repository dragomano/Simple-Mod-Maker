<?php

return [
	'params' => [
		'actions' => ['array'],
	],
	'return' => '?string',
	'body' => $context['smm_skeleton']['smf_target_version'] !== '3.0' ? [
		"global \$txt, \$scripturl;" . PHP_EOL,
		"if (empty(\$actions['action']) || \$actions['action'] !== 'your_action')",
		"\treturn '';" . PHP_EOL,
		"\$result = sprintf(\$txt['your_mod_who_viewing_your_action'], \$scripturl . '?action=' . \$actions['action']);" . PHP_EOL,
		"if (isset(\$actions['subaction'])) {",
		"\t\$result = sprintf(\$txt['your_mod_who_viewing_your_subaction'], \$scripturl . '?action=' . \$actions['action'] . ';' . \$actions['subaction']);",
		"}" . PHP_EOL,
		"return \$result;",
	] : [
		"if (empty(\$actions['action']) || \$actions['action'] !== 'your_action')",
		"\treturn '';" . PHP_EOL,
		"\$result = Lang::getTxt('your_mod_who_viewing_your_action', [",
		"\tConfog::\$scripturl . '?action=' . \$actions['action']",
		"]);" . PHP_EOL,
		"if (isset(\$actions['subaction'])) {",
		"\t\$result = Lang::getTxt('your_mod_who_viewing_your_subaction', [",
		"\t\tConfig::\$scripturl . '?action=' . \$actions['action'] . ';' . \$actions['subaction']",
		"\t]);",
		"}" . PHP_EOL,
		"return \$result;",
	],
];
