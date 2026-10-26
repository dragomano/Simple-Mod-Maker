<?php declare(strict_types=1);

namespace Tests;

use Bugo\SimpleModMaker\Hooks\AbstractHook;
use Bugo\SimpleModMaker\Hooks\AlertTypesHook;
use Bugo\SimpleModMaker\Hooks\CreditsHook;
use Bugo\SimpleModMaker\Hooks\DownloadRequestHook;
use Bugo\SimpleModMaker\Hooks\FetchAlertsHook;
use Bugo\SimpleModMaker\Hooks\LoadIllegalGuestPermissionsHook;
use Bugo\SimpleModMaker\Hooks\WhosOnlineHook;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The hooks that have two bodies: one for SMF 2.1 on the legacy globals and one
 * for SMF 3.0 on the service classes of the core. A package built for a single
 * target takes the matching body, while a mixed package gets both variants
 * behind a version check.
 */
#[CoversClass(AlertTypesHook::class)]
#[CoversClass(CreditsHook::class)]
#[CoversClass(DownloadRequestHook::class)]
#[CoversClass(FetchAlertsHook::class)]
#[CoversClass(LoadIllegalGuestPermissionsHook::class)]
#[CoversClass(WhosOnlineHook::class)]
class TargetVersionHooksTest extends TestCase
{
	#[Test]
	public function alertTypesHookCarriesTheAlertDeclarationForBothTargets(): void
	{
		$legacy = $this->hook(AlertTypesHook::class, '2.1');
		$modern = $this->hook(AlertTypesHook::class, '3.0');

		$this->assertSame('integrate_alert_types', $legacy->getName());
		$this->assertSame('alertTypes', $legacy->getMethodName());
		$this->assertSame(
			['alert_types' => ['array', true], 'group_options' => ['array', true]],
			$legacy->getParameters()
		);

		$alertTypeCode = "\$alert_types['demo']['some_action'] = [\n"
			. "\t'alert' => 'yes', // or 'never'\n"
			. "\t'email' => 'yes', // or 'never'\n"
			. "\t'permission' => [\n"
			. "\t\t'name'     => 'some_permission',\n"
			. "\t\t'is_board' => false,\n"
			. "\t]\n"
			. "];";

		$this->assertSame(
			['global $txt;' . "\n", "\$txt['alert_group_demo'] = \$txt['demo_title'];" . "\n", $alertTypeCode],
			self::eol($legacy->getBody())
		);

		// The group title moves to the Lang class, the declaration itself is shared
		$this->assertSame(
			["Lang::\$txt['alert_group_demo'] = Lang::\$txt['demo_title'];" . "\n", $alertTypeCode],
			self::eol($modern->getBody())
		);
	}

	#[Test]
	public function creditsHookNamesTheModAuthorPerTarget(): void
	{
		$skeleton = ['name' => 'Demo Mod', 'author' => 'Bugo'];

		$legacy = $this->hook(CreditsHook::class, '2.1', $skeleton);
		$modern = $this->hook(CreditsHook::class, '3.0', $skeleton);

		$this->assertSame([], $legacy->getParameters());

		$year = date('Y');

		$this->assertSame(
			['global $context;' . "\n", "\$context['copyrights']['mods'][] = 'Demo Mod by Bugo &copy; $year';"],
			self::eol($legacy->getBody())
		);

		$this->assertSame(
			["Utils::\$context['copyrights']['mods'][] = 'Demo Mod by Bugo &copy; $year';"],
			self::eol($modern->getBody())
		);
	}

	#[Test]
	public function downloadRequestHookQueriesTheAttachmentPerTarget(): void
	{
		$legacy = $this->hook(DownloadRequestHook::class, '2.1');
		$modern = $this->hook(DownloadRequestHook::class, '3.0');

		$this->assertSame(['attachRequest' => ['array', true]], $legacy->getParameters());

		$guard = [
			"if ((!empty(\$attachRequest) && is_resource(\$attachRequest)) || empty(\$_REQUEST['item']))",
			"\treturn;" . "\n",
		];

		$params = [
			"\t[",
			"\t\t'attach' => (int) \$_REQUEST['attach'],",
			"\t\t'item'   => (int) \$_REQUEST['item'],",
			"\t]",
			");",
		];

		$this->assertSame(
			array_merge(
				['global $smcFunc;' . "\n"],
				$guard,
				["\$attachRequest = \$smcFunc['db_query']('', 'Here is your SQL',"],
				$params
			),
			self::eol($legacy->getBody())
		);

		$this->assertSame(
			array_merge(
				$guard,
				["\$attachRequest = Db::\$db->query('', 'Here is your SQL',"],
				$params
			),
			self::eol($modern->getBody())
		);
	}

	#[Test]
	public function fetchAlertsHookFormatsTheAlertsPerTarget(): void
	{
		$legacy = $this->hook(FetchAlertsHook::class, '2.1');
		$modern = $this->hook(FetchAlertsHook::class, '3.0');

		$this->assertSame(['alerts' => ['array', true], 'formats' => ['array', true]], $legacy->getParameters());

		$guard = ['if (empty($alerts))', "\treturn;" . "\n"];

		$loop = [
			'foreach ($alerts as $id => $alert) {',
			"\tif (\$alert['content_action'] === 'some_action') {",
		];

		$format = [
			"\t\t\t\$formats['some_action_format'] = [",
			"\t\t\t\t'required' => ['content_subject', 'content_link'],",
			"\t\t\t\t'link'     => '<a href=\"%2\$s\">%1\$s</a>',",
			"\t\t\t\t'text'     => '<strong>%1\$s</strong>'",
			"\t\t\t];",
			"\t\t} else {",
			"\t\t\tunset(\$alerts[\$id]);",
			"\t\t}",
			"\t}",
			'}',
		];

		$icon = "\t\t\t\$alerts[\$id]['icon'] = '<span class=\"alert_icon main_icons some_icon\"></span>';" . "\n";

		$this->assertSame(
			array_merge(
				['global $user_info;' . "\n"],
				$guard,
				$loop,
				["\t\tif (\$alert['sender_id'] !== \$user_info['id']) {"],
				[
					$icon,
					"\t\t\t// \$txt['alert_some_action'] = 'Short description of this action';",
					"\t\t\t// \$txt['alert_item_some_action'] = '{member_link} did something {some_action_format}';",
				],
				$format
			),
			self::eol($legacy->getBody())
		);

		$this->assertSame(
			array_merge(
				$guard,
				$loop,
				["\t\tif (\$alert['sender_id'] !== \$User::info['id']) {"],
				[
					$icon,
					"\t\t\t// Lang::\$txt['alert_some_action'] = 'Short description of this action';",
					"\t\t\t// Lang::\$txt['alert_item_some_action'] = '{member_link} did something {some_action_format}';",
				],
				$format
			),
			self::eol($modern->getBody())
		);
	}

	#[Test]
	public function loadIllegalGuestPermissionsHookListsThePermissionPerTarget(): void
	{
		$legacy = $this->hook(LoadIllegalGuestPermissionsHook::class, '2.1');
		$modern = $this->hook(LoadIllegalGuestPermissionsHook::class, '3.0');

		$this->assertSame('integrate_load_illegal_guest_permissions', $legacy->getName());
		$this->assertSame('loadIllegalGuestPermissions', $legacy->getMethodName());
		$this->assertSame([], $legacy->getParameters());

		$this->assertSame(
			['global $context;' . "\n", "\$context['non_guest_permissions'][] = 'demo_example_permission';"],
			self::eol($legacy->getBody())
		);

		$this->assertSame(
			["Utils::\$context['non_guest_permissions'][] = 'demo_example_permission';"],
			self::eol($modern->getBody())
		);
	}

	#[Test]
	public function whosOnlineHookReportsTheActionPerTarget(): void
	{
		$legacy = $this->hook(WhosOnlineHook::class, '2.1');
		$modern = $this->hook(WhosOnlineHook::class, '3.0');

		$this->assertSame('integrate_whos_online', $legacy->getName());
		$this->assertSame('whosOnline', $legacy->getMethodName());
		$this->assertSame(['actions' => ['array']], $legacy->getParameters());

		// The report is either a string or nothing at all
		$this->assertSame('?string', $legacy->getReturnType());
		$this->assertSame('?string', $modern->getReturnType());

		$this->assertSame(
			[
				'global $txt, $scripturl;' . "\n",
				"if (empty(\$actions['action']) || \$actions['action'] !== 'your_action') {",
				"\treturn '';",
				'}' . "\n",
				"\$result = sprintf(\$txt['your_mod_who_viewing_your_action'], \$scripturl . '?action=' . \$actions['action']);" . "\n",
				"if (isset(\$actions['subaction'])) {",
				"\t\$result = sprintf(\$txt['your_mod_who_viewing_your_subaction'], \$scripturl . '?action=' . \$actions['action'] . ';' . \$actions['subaction']);",
				'}' . "\n",
				'return $result;',
			],
			self::eol($legacy->getBody())
		);

		$this->assertSame(
			[
				"if (empty(\$actions['action']) || \$actions['action'] !== 'your_action') {",
				"\treturn '';",
				'}' . "\n",
				"\$result = Lang::getTxt('your_mod_who_viewing_your_action', [",
				"\tConfig::\$scripturl . '?action=' . \$actions['action']",
				']);' . "\n",
				"if (isset(\$actions['subaction'])) {",
				"\t\$result = Lang::getTxt('your_mod_who_viewing_your_subaction', [",
				"\t\tConfig::\$scripturl . '?action=' . \$actions['action'] . ';' . \$actions['subaction']",
				"\t]);",
				'}' . "\n",
				'return $result;',
			],
			self::eol($modern->getBody())
		);
	}

	#[Test]
	#[DataProvider('dualHooks')]
	public function bothVariantsOfAHookAreHandedToMixedPackages(string $className): void
	{
		$legacy = $this->hook($className, '2.1');
		$modern = $this->hook($className, '3.0');

		$dual = $legacy->getDualBody();

		$this->assertNotNull($dual);

		// The body of a single-target package is the matching half of the pair
		$this->assertSame(self::eol($legacy->getBody()), self::eol($dual['legacy']));
		$this->assertSame(self::eol($modern->getBody()), self::eol($dual['modern']));

		$this->assertNotSame($dual['legacy'], $dual['modern'], 'The variants must really differ');
	}

	public static function dualHooks(): array
	{
		return [
			'AlertTypesHook'                    => [AlertTypesHook::class],
			'CreditsHook'                       => [CreditsHook::class],
			'DownloadRequestHook'               => [DownloadRequestHook::class],
			'FetchAlertsHook'                   => [FetchAlertsHook::class],
			'LoadIllegalGuestPermissionsHook'   => [LoadIllegalGuestPermissionsHook::class],
			'WhosOnlineHook'                    => [WhosOnlineHook::class],
		];
	}

	private function hook(string $className, string $targetVersion, array $skeleton = []): AbstractHook
	{
		$skeleton = array_merge(['name' => 'Demo Mod', 'author' => 'Bugo', 'smf_target_version' => $targetVersion], $skeleton);

		return new $className(['smm_skeleton' => $skeleton], 'Demo', 'demo');
	}

	/**
	 * The generated bodies keep PHP_EOL as their line separator, so the
	 * platform-specific form is reduced to the common one before the comparison.
	 *
	 * @param list<string> $lines
	 * @return list<string>
	 */
	private static function eol(array $lines): array
	{
		return array_map(static fn (string $line): string => str_replace(PHP_EOL, "\n", $line), $lines);
	}
}
