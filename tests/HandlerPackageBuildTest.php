<?php declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * The successful end of the generation flow downloads the package, which
 * prints the archive to STDOUT and exits — impossible inside PHPUnit. The
 * whole saved submission therefore runs in a separate process, and this
 * test validates the streamed archive and the remembered settings.
 */
class HandlerPackageBuildTest extends TestCase
{
	private string $tempBase;

	protected function setUp(): void
	{
		$this->tempBase = sys_get_temp_dir() . '/smm_package_build_' . bin2hex(random_bytes(4));
	}

	protected function tearDown(): void
	{
		deltree($this->tempBase);
	}

	#[Test]
	public function savedSubmissionStreamsAReadyPackage(): void
	{
		$packagesDir = $this->tempBase . '/Packages';
		$reportFile  = $this->tempBase . '/report.json';

		$params = [
			'post'          => $this->fullPostData(),
			'mod_settings'  => ['smm_hooks' => 'integrate_user_info'],
			'packages_dir'  => $packagesDir,
			'sc'            => 'sub-check',
			'txt'           => $this->langStrings(),
			'languages'     => [
				['filename' => 'english', 'name' => 'English'],
				['filename' => 'russian', 'name' => 'Русский'],
			],
			'report_file'   => $reportFile,
		];

		$output = $this->runGeneratorProcess($params);

		// The archive went straight to STDOUT
		$this->assertStringStartsWith("PK\x03\x04", $output, 'The download must stream a ZIP archive.');

		$archive = $this->tempBase . '/downloaded.zip';
		file_put_contents($archive, $output);

		$zip = new ZipArchive();

		$this->assertTrue($zip->open($archive));

		$entries = [];

		for ($i = 0; $i < $zip->numFiles; $i++) {
			$entries[] = str_replace('\\', '/', (string) $zip->getNameIndex($i));
		}

		$zip->close();

		sort($entries);

		// The posted make_dir flag puts the sources into their own directory
		foreach ([
			'package-info.xml',
			'license.txt',
			'database.php',
			'Sources/Demo/Integration.php',
			'Sources/Demo/Tasks/DailyCleanup.php',
			'Sources/Demo/Tasks/CleanupBackground.php',
			'Themes/default/Demo.template.php',
			'Themes/default/languages/Demo/.english.php',
			'Themes/default/languages/Demo/.russian.php',
		] as $expected) {
			$this->assertContains($expected, $entries, $expected);
		}

		// The used hooks and the vendor are remembered for the next builds
		$report = json_decode((string) file_get_contents($reportFile), true);

		$this->assertArrayHasKey('error', $report);
		$this->assertNull($report['error']);
		$this->assertSame('MyVendor\\MyMods', $report['settings_updates'][1]['smm_mod_namespace']);
		$this->assertSame(
			'integrate_load_theme,integrate_buffer,integrate_weekly_maintenance,'
			. 'integrate_autoload,integrate_general_mod_settings,integrate_credits,integrate_user_info',
			$report['settings_updates'][0]['smm_hooks']
		);

		// The working folder stays in Packages, ready for an immediate install
		$this->assertCount(1, $report['packages']);

		$package = $report['packages'][0];

		$this->assertMatchesRegularExpression('/demo_1\.0_[0-9a-f]{16}$/', $package);

		foreach (['package-info.xml', 'license.txt', 'Sources/Demo/Integration.php'] as $file) {
			$this->assertFileExists($package . '/' . $file, $file);
		}
	}

	/**
	 * @param array<string, mixed> $params
	 */
	private function runGeneratorProcess(array $params): string
	{
		mkdir($params['packages_dir'], 0777, true);

		$paramsFile = $this->tempBase . '/params.json';
		$scriptFile = $this->tempBase . '/runner.php';
		$outputFile = $this->tempBase . '/stdout.bin';
		$stderrFile = $this->tempBase . '/stderr.txt';

		file_put_contents($paramsFile, json_encode($params));
		file_put_contents(
			$scriptFile,
			"<?php declare(strict_types=1);\n\n"
			. 'require ' . var_export(__DIR__ . '/HandlerPackageRunner.php', true) . ";\n\n"
			. 'HandlerPackageRunner::run(json_decode((string) file_get_contents('
			. var_export($paramsFile, true) . '), true));' . "\n"
		);

		$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($scriptFile)
			. ' > ' . escapeshellarg($outputFile)
			. ' 2> ' . escapeshellarg($stderrFile);

		exec($command, $output, $exitCode);

		$stderr = (string) file_get_contents($stderrFile);

		$this->assertSame(
			0,
			$exitCode,
			$stderr . "\n" . @file_get_contents($params['report_file'])
		);
		$this->assertSame('', $stderr, 'The generation must not emit anything on STDERR.');

		return (string) file_get_contents($outputFile);
	}

	private function fullPostData(): array
	{
		return [
			'save'                         => '1',
			'name'                         => 'Demo Mod',
			'filename'                     => 'Class-Demo',
			'namespace'                    => 'MyVendor\\MyMods',
			'hooks'                        => ['integrate_load_theme', 'integrate_buffer'],
			'version'                      => '1.0',
			'site'                         => 'https://example.test',
			'license'                      => 'own',
			'min_php_version'              => '8.2',
			'smf_target_version'           => '2.1/3.0',
			'settings_area'                => '1',
			'title_english'                => 'Demo',
			'description_english'          => 'Desc',
			'title_russian'                => 'Демо',
			'description_russian'          => 'Описание',
			'option_names'                 => [0 => 'enable_feature', 1 => 'choice', 2 => 'custom_block'],
			'option_types'                 => [0 => 'check', 1 => 'select', 2 => 'callback'],
			'option_defaults'              => [0 => 'on', 1 => 'a'],
			'option_variants'              => [1 => 'a|b'],
			'option_translations'          => [
				'english' => [0 => 'Enable feature'],
				'russian' => [0 => 'Включить', 1 => 'Выбор'],
			],
			'table_names'                  => [0 => 'demo_table'],
			'column_names'                 => [0 => [0 => 'id', 1 => 'title', 2 => 'notes']],
			'column_types'                 => [0 => [0 => 'int', 1 => 'varchar', 2 => 'text']],
			'column_auto'                  => [0 => [0 => 'on']],
			'column_sizes'                 => [0 => [1 => '100']],
			'column_null'                  => [0 => [2 => 'on']],
			'column_defaults'              => [0 => [1 => 'none']],
			'task_slugs'                   => [0 => 'daily_cleanup'],
			'task_regularities'            => [0 => '0'],
			'task_names'                   => ['russian' => [0 => 'Очистка']],
			'task_descriptions'            => ['russian' => [0 => 'Чистит данные']],
			'background_task_classnames'   => [0 => 'CleanupBackground'],
			'background_task_regularities' => [0 => '0'],
			'legacy_task_methods'          => [0 => 'runWeekly'],
			'legacy_task_regularities'     => [0 => '1'],
			'make_dir'                     => 'on',
			'use_lang_dir'                 => 'on',
			'make_template'                => 'on',
			'make_script'                  => 'on',
			'make_css'                     => 'on',
			'make_readme'                  => 'on',
			'add_copyrights'               => 'on',
		];
	}

	private function langStrings(): array
	{
		global $txt;

		$txt = [];

		require dirname(__DIR__) . '/src/Themes/default/languages/SimpleModMaker/.english.php';

		$txt['email']   = 'user@example.test';
		$txt['website'] = 'Website';

		return $txt;
	}
}
