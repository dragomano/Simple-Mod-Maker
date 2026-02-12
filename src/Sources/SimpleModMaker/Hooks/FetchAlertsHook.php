<?php declare(strict_types=1);

/**
 * @package Simple Mod Maker
 * @link https://github.com/dragomano/Simple-Mod-Maker
 * @author Bugo <bugo@dragomano.ru>
 * @copyright 2022-2026 Bugo
 * @license https://opensource.org/licenses/BSD-3-Clause BSD
 *
 * @version 1.0
 */

namespace Bugo\SimpleModMaker\Hooks;

class FetchAlertsHook extends AbstractHook
{
	protected function defineName(): string
	{
		return 'integrate_fetch_alerts';
	}

	public function getParameters(): array
	{
		return [
			'alerts'  => ['array', true],
			'formats' => ['array', true],
		];
	}

	public function getBody(): array
	{
		return $this->context['smm_skeleton']['smf_target_version'] !== '3.0' ? [
			"global \$user_info;" . PHP_EOL,
			"if (empty(\$alerts))",
			"\treturn;" . PHP_EOL,
			"foreach (\$alerts as \$id => \$alert) {",
			"\tif (\$alert['content_action'] === 'some_action') {",
			"\t\tif (\$alert['sender_id'] !== \$user_info['id']) {",
			"\t\t\t\$alerts[\$id]['icon'] = '<span class=\"alert_icon main_icons some_icon\"></span>';" . PHP_EOL,
			"\t\t\t// \$txt['alert_some_action'] = 'Short description of this action';",
			"\t\t\t// \$txt['alert_item_some_action'] = '{member_link} did something {some_action_format}';",
			"\t\t\t\$formats['some_action_format'] = [",
			"\t\t\t\t'required' => ['content_subject', 'content_link'],",
			"\t\t\t\t'link'     => '<a href=\"%2\$s\">%1\$s</a>',",
			"\t\t\t\t'text'     => '<strong>%1\$s</strong>'",
			"\t\t\t];",
			"\t\t} else {",
			"\t\t\tunset(\$alerts[\$id]);",
			"\t\t}",
			"\t}",
			"}"
		] : [
			"if (empty(\$alerts))",
			"\treturn;" . PHP_EOL,
			"foreach (\$alerts as \$id => \$alert) {",
			"\tif (\$alert['content_action'] === 'some_action') {",
			"\t\tif (\$alert['sender_id'] !== \$User::info['id']) {",
			"\t\t\t\$alerts[\$id]['icon'] = '<span class=\"alert_icon main_icons some_icon\"></span>';" . PHP_EOL,
			"\t\t\t// Lang::\$txt['alert_some_action'] = 'Short description of this action';",
			"\t\t\t// Lang::\$txt['alert_item_some_action'] = '{member_link} did something {some_action_format}';",
			"\t\t\t\$formats['some_action_format'] = [",
			"\t\t\t\t'required' => ['content_subject', 'content_link'],",
			"\t\t\t\t'link'     => '<a href=\"%2\$s\">%1\$s</a>',",
			"\t\t\t\t'text'     => '<strong>%1\$s</strong>'",
			"\t\t\t];",
			"\t\t} else {",
			"\t\t\tunset(\$alerts[\$id]);",
			"\t\t}",
			"\t}",
			"}"
		];
	}
}
