<?php declare(strict_types=1);

namespace Tests;

use Bugo\SimpleModMaker\Handler;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

#[CoversMethod(Handler::class, 'searchHooks')]
#[CoversMethod(Handler::class, 'filterHookList')]
#[CoversMethod(Handler::class, 'getHookList')]
#[CoversMethod(Handler::class, 'prepareHookList')]
class HandlerHookSearchTest extends TestCase
{
	private Handler $handler;

	protected function setUp(): void
	{
		global $modSettings, $context;

		$this->handler = new Handler();

		$modSettings = [];
		$context     = [];
		$_REQUEST    = [];
	}

	public function testPrepareHookListIgnoresEmptyAndDuplicateEntries(): void
	{
		global $modSettings, $context;

		$modSettings['smm_hooks'] = 'integrate_load_theme,,integrate_user_info';

		(new ReflectionMethod($this->handler, 'prepareHookList'))->invoke($this->handler, [
			'hooks' => ['integrate_load_theme', 'integrate_actions'],
		]);

		$this->assertSame(
			[
				['text' => 'integrate_actions', 'value' => 'integrate_actions'],
				['text' => 'integrate_load_theme', 'value' => 'integrate_load_theme'],
				['text' => 'integrate_user_info', 'value' => 'integrate_user_info'],
			],
			$context['smm_hook_list']['data']
		);

		$this->assertSame(
			['integrate_load_theme', 'integrate_actions'],
			$context['smm_hook_list']['items']
		);
	}

	public function testNativeSelectOptionsMarkTheHooksChosenInSkeleton(): void
	{
		global $modSettings, $context;

		$modSettings['smm_hooks'] = 'integrate_user_info';

		(new ReflectionMethod($this->handler, 'prepareHookList'))->invoke($this->handler, [
			'hooks' => ['integrate_load_theme'],
		]);

		$this->assertSame(
			[
				'integrate_load_theme' => ['value' => 'integrate_load_theme', 'selected' => true],
				'integrate_user_info'  => ['value' => 'integrate_user_info', 'selected' => false],
			],
			$context['smm_hook_list']['select_options']
		);
	}

	public function testSparseSearchResultIsReindexedIntoJsonArray(): void
	{
		// A match in the middle of a cached list leaves sparse keys behind
		$hooks = [0 => 'integrate_actions', 9 => 'integrate_buffer', 17 => 'integrate_load_theme'];

		$results = (new ReflectionMethod($this->handler, 'filterHookList'))->invoke($this->handler, $hooks, 'buffer');

		$this->assertSame([['value' => 'integrate_buffer']], $results);
		$this->assertStringStartsWith('[', json_encode($results));
	}

	public function testEmptySearchResultIsEncodedAsArray(): void
	{
		$results = (new ReflectionMethod($this->handler, 'filterHookList'))->invoke($this->handler, [
			'integrate_actions',
		], 'nothing_matches_this');

		$this->assertSame([], $results);
		$this->assertSame('[]', json_encode($results));
	}

	public function testHookSearchEndpointAnswersWithEmptyArrayForInvalidBody(): void
	{
		$output = $this->runIsolatedHandler([], 'search_hooks', '');

		$this->assertSame('[]', $output[0] ?? '');
	}

	public function testFormSubmissionWithSelectedHooksIsNotIntercepted(): void
	{
		// A submitted form carries the selected hooks as an array, and the
		// generator flow must continue instead of answering with JSON
		$output = $this->runIsolatedHandler([], 'form_submission', ['integrate_load_theme']);

		$this->assertSame(['form_flow'], $output);
	}

	public function testSearchMatchingMiddleOfListReturnsJsonArray(): void
	{
		$modSettings = [
			'integrate_load_theme'  => 'SomeClass::method', // Already present in the built-in list
			'integrate_custom_hook' => 'OtherClass::method',
		];

		$output = $this->runIsolatedHandler($modSettings, 'filter_hooks', search: 'load_theme');

		$this->assertStringStartsWith('[', $output[0] ?? '');

		$this->assertSame(
			[['value' => 'integrate_load_theme']],
			json_decode($output[0] ?? '', true)
		);
	}

	private function runIsolatedHandler(array $modSettings, string $scenario, array|string|null $hooksParam = null, string $search = ''): array
	{
		$script = "<?php declare(strict_types=1);\n\n";

		$script .= "define('SMF', true);\n\n";
		$script .= "\$modSettings = " . var_export($modSettings, true) . ";\n\n";

		// The same modSettings-based lookup as in Subs.php, so the isolated
		// process does not need the SMF distribution files
		$script .= "function get_integration_hooks(): array {\n";
		$script .= "\treturn array_filter(\$GLOBALS['modSettings'] ?? [], fn(\$value, \$key) => str_starts_with((string) \$key, 'integrate_') && \$value !== '', ARRAY_FILTER_USE_BOTH);\n";
		$script .= "}\n\n";

		$script .= "require_once " . var_export(dirname(__DIR__) . '/src/Sources/SimpleModMaker/Hook.php', true) . ";\n";
		$script .= "require_once " . var_export(dirname(__DIR__) . '/src/Sources/SimpleModMaker/Hooks/HookFactory.php', true) . ";\n";
		$script .= "require_once " . var_export(dirname(__DIR__) . '/src/Sources/SimpleModMaker/Handler.php', true) . ";\n\n";
		$script .= "\$handler = new \\Bugo\\SimpleModMaker\\Handler();\n\n";

		if ($scenario === 'filter_hooks') {
			$script .= "\$hooks = (new ReflectionMethod(\$handler, 'getHookList'))->invoke(\$handler);\n";
			$script .= "\$results = (new ReflectionMethod(\$handler, 'filterHookList'))->invoke(\$handler, \$hooks, "
				. var_export($search, true) . ");\n\n";
			$script .= "echo json_encode(\$results);\n";
		} else {
			$script .= "\$_REQUEST['hooks'] = " . var_export($hooksParam, true) . ";\n\n";

			if ($scenario === 'search_hooks') {
				// An empty php://input is the closest CLI emulation of a broken request body
				$script .= "(new ReflectionMethod(\$handler, 'searchHooks'))->invoke(\$handler);\n";
			} else {
				$script .= "(new ReflectionMethod(\$handler, 'searchHooks'))->invoke(\$handler);\n\n";
				$script .= "echo 'form_flow';\n";
			}
		}

		$file = tempnam(sys_get_temp_dir(), 'smm_hooks_') . '.php';

		try {
			file_put_contents($file, $script);

			exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1', $output, $exitCode);
		} finally {
			@unlink($file);
		}

		$this->assertSame(0, $exitCode, implode("\n", $output) . "\n---\n" . $script);

		return $output;
	}
}
