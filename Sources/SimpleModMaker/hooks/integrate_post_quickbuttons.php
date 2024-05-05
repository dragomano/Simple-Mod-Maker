<?php

return [
	'params' => [
		'list_items' => ['array', true],
	],
	'body' => [
		"/*",
		"\$list_items['my_button'] = [",
		"\t'label' => 'Label text',",
		"\t'href'  => 'https://site.domain',",
		"\t'icon'  => 'home',",
		"\t'show'  => true",
		"];",
		"*/",
	]
];
