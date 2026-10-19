<?php declare(strict_types=1);

namespace Bugo\SimpleModMaker;

if (! defined('SMF'))
	die('No direct access...');

final class TemplateUtil
{
	public static function needsTemplate(array $skeleton): bool
	{
		return ! empty($skeleton['make_template']) || ! empty($skeleton['callbacks']);
	}
}
