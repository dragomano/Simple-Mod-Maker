<?php declare(strict_types=1);

/**
 * @package Simple Mod Maker
 * @link https://github.com/dragomano/Simple-Mod-Maker
 * @author Bugo <bugo@dragomano.ru>
 * @copyright 2022-2025 Bugo
 * @license https://opensource.org/licenses/BSD-3-Clause BSD
 *
 * @version 0.9
 */

namespace Bugo\SimpleModMaker\Hooks;

class DownloadRequestHook extends AbstractHook
{
	protected function defineName(): string
	{
		return 'integrate_download_request';
	}

	public function getParameters(): array
	{
		return [
			'attachRequest' => ['array', true],
		];
	}

	public function getBody(): array
	{
		return $this->context['smm_skeleton']['smf_target_version'] !== '3.0' ? [
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
		];
	}
}
