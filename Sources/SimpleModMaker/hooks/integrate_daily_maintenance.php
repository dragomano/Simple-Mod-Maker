<?php

$tasks = '';

foreach ($context['smm_skeleton']['legacy_tasks'] as $id => $task) {
	if (empty($task['regularity'])) {
		$tasks .= "\$this->{$task['method']};";
	}
}

return [
	'params' => [],
	'body' => [
		$tasks,
	]
];
