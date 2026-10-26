<?php declare(strict_types=1);

namespace Tests;

use Bugo\SimpleModMaker\Hooks\DailyMaintenanceHook;
use Bugo\SimpleModMaker\Hooks\HookFactory;
use Bugo\SimpleModMaker\Hooks\WeeklyMaintenanceHook;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DailyMaintenanceHook::class)]
#[CoversClass(WeeklyMaintenanceHook::class)]
	#[CoversMethod(DailyMaintenanceHook::class, 'getName')]
	#[CoversMethod(DailyMaintenanceHook::class, 'getBody')]
	#[CoversMethod(WeeklyMaintenanceHook::class, 'getName')]
	#[CoversMethod(WeeklyMaintenanceHook::class, 'getBody')]
	#[CoversMethod(WeeklyMaintenanceHook::class, 'getParameters')]
class MaintenanceHooksTest extends TestCase
{
	#[Test]
	public function maintenanceHooksTakeNoParameters(): void
	{
		$daily  = new DailyMaintenanceHook(['smm_skeleton' => []], 'Demo', 'demo');
		$weekly = new WeeklyMaintenanceHook(['smm_skeleton' => []], 'Demo', 'demo');

		// Both hooks are called by the scheduled task runner without arguments
		$this->assertSame([], $daily->getParameters());
		$this->assertSame([], $weekly->getParameters());
	}
	#[Test]
	public function dailyHookCallsLegacyMethodsWithoutRegularities(): void
	{
		$hook = new DailyMaintenanceHook([
			'smm_skeleton' => [
				'legacy_tasks' => [
					['method' => 'runDaily', 'regularity' => 0],
					['method' => 'runWeekly', 'regularity' => 1],
				],
			],
		], 'Demo', 'demo');

		$this->assertEquals('integrate_daily_maintenance', $hook->getName());

		$body = $hook->getBody();

		$this->assertContains('$this->runDaily();', $body);
		$this->assertNotContains('$this->runWeekly();', $body);
		$this->assertNotContains('$this->runDaily;', $body);
	}

	#[Test]
	public function dailyHookFallsBackToCommentWithoutLegacyTasks(): void
	{
		$hook = new DailyMaintenanceHook(['smm_skeleton' => []], 'Demo', 'demo');

		$body = $hook->getBody();

		$this->assertIsArray($body);
		$this->assertNotEmpty($body);
		$this->assertStringContainsStringIgnoringCase('daily', $body[0]);
	}

	#[Test]
	public function weeklyHookCallsLegacyMethodsWithRegularities(): void
	{
		$hook = new WeeklyMaintenanceHook([
			'smm_skeleton' => [
				'legacy_tasks' => [
					['method' => 'runDaily', 'regularity' => 0],
					['method' => 'runWeekly', 'regularity' => 1],
				],
			],
		], 'Demo', 'demo');

		$this->assertEquals('integrate_weekly_maintenance', $hook->getName());

		$body = implode("\n", $hook->getBody());

		$this->assertStringContainsString('$this->runWeekly();', $body);
		$this->assertStringNotContainsString('$this->runDaily();', $body);
		$this->assertStringNotContainsString('$this->runWeekly;', $body);
	}

	#[Test]
	public function weeklyHookIsRegisteredInFactory(): void
	{
		$hook = HookFactory::create('integrate_weekly_maintenance');

		$this->assertNotNull($hook);
		$this->assertInstanceOf(WeeklyMaintenanceHook::class, $hook);
		$this->assertEquals('integrate_weekly_maintenance', $hook->getName());
	}

	#[Test]
	public function generatedBodiesActuallyRunTheMappedMethods(): void
	{
		$legacyTasks = [
			['method' => 'runDaily', 'regularity' => 0],
			['method' => 'runWeekly', 'regularity' => 1],
		];

		$daily  = (new DailyMaintenanceHook(['smm_skeleton' => ['legacy_tasks' => $legacyTasks]], 'Demo', 'demo'))->getBody();
		$weekly = (new WeeklyMaintenanceHook(['smm_skeleton' => ['legacy_tasks' => $legacyTasks]], 'Demo', 'demo'))->getBody();

		// Build a standalone script where the generated bodies are executed
		// inside methods of a stub class, and the actual calls are reported.
		$script = "<?php\n"
			. "class MaintenanceStub\n"
			. "{\n"
			. "\tpublic \$calls = [];\n"
			. "\tpublic function runDaily(): void { \$this->calls[] = 'runDaily'; }\n"
			. "\tpublic function runWeekly(): void { \$this->calls[] = 'runWeekly'; }\n"
			. "\tpublic function dailyMaintenance(): void\n"
			. "\t{\n"
			. "\t\t" . implode("\n\t\t", $daily) . "\n"
			. "\t}\n"
			. "\tpublic function weeklyMaintenance(): void\n"
			. "\t{\n"
			. "\t\t" . implode("\n\t\t", $weekly) . "\n"
			. "\t}\n"
			. "}\n"
			. "\$stub = new MaintenanceStub();\n"
			. "\$stub->dailyMaintenance();\n"
			. "\$stub->weeklyMaintenance();\n"
			. "echo json_encode(\$stub->calls);\n";

		$file   = tempnam(sys_get_temp_dir(), 'smm_maintenance_') . '.php';
		$output = [];

		try {
			file_put_contents($file, $script);

			exec(
				escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1',
				$output,
				$exitCode
			);

			$this->assertSame(0, $exitCode, implode("\n", $output) . "\n---\n" . $script);
			$this->assertSame(['runDaily', 'runWeekly'], json_decode($output[0] ?? '', true));
		} finally {
			@unlink($file);
		}
	}
}
