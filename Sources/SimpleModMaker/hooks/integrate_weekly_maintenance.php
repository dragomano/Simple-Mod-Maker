<?php

$tasks = '';

foreach ($context['smm_skeleton']['legacy_tasks'] as $task) {
	if (empty($task['regularity']))
		continue;

	$tasks .= "\$this->{$task['method']};";
}

return [
	'params' => [],
	'body' => [
		$tasks,
	]
];
