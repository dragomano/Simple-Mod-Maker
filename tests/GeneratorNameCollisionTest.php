<?php declare(strict_types=1);

namespace Tests;

use Bugo\SimpleModMaker\Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Generator::class)]
class GeneratorNameCollisionTest extends TestCase
{
	#[Test]
	public function taskClassesNamedLikeTheirParentsGenerateValidCode(): void
	{
		$skeleton = $this->skeleton([
			'smf_target_version' => '3.0',
			'make_dir'           => true,
			'hooks'              => ['integrate_admin_areas'],
			'scheduled_tasks'    => [
				0 => [
					'slug'         => 'scheduled_task',
					'names'        => [],
					'descriptions' => [],
					'regularity'   => 0,
				],
			],
			'background_tasks' => [
				0 => [
					'classname'  => 'BackgroundTask',
					'regularity' => '',
				],
			],
		]);

		$result = (new Generator())->generate($skeleton, 'Demo', 'demo');

		$this->assertArrayHasKey('scheduled_task', $result['tasks']);
		$this->assertArrayHasKey('BackgroundTask', $result['tasks']);

		foreach ($result['tasks'] as $task) {
			$this->lint($task['content']);
		}

		$this->assertStringNotContainsString(
			'use SMF\Tasks\ScheduledTask;',
			$result['tasks']['scheduled_task']['content']
		);
		$this->assertStringContainsString(
			'extends \SMF\Tasks\ScheduledTask',
			$result['tasks']['scheduled_task']['content']
		);
		$this->assertStringNotContainsString(
			'use SMF\Tasks\BackgroundTask;',
			$result['tasks']['BackgroundTask']['content']
		);
		$this->assertStringContainsString(
			'extends \SMF\Tasks\BackgroundTask',
			$result['tasks']['BackgroundTask']['content']
		);
	}

	#[Test]
	public function runExampleMethodReferencesTaskClassViaImport(): void
	{
		$skeleton = $this->skeleton([
			'background_tasks' => [
				0 => [
					'classname'  => 'CleanupBackground',
					'regularity' => '',
				],
			],
		]);

		$result = (new Generator())->generate($skeleton, 'Demo', 'demo');

		$this->lint($result['content']);
		$this->assertStringContainsString(
			'use Bugo\Demo\Tasks\CleanupBackground;',
			$result['content']
		);
		$this->assertStringContainsString(
			"'\\\\' . CleanupBackground::class,",
			$result['content']
		);
	}

	#[Test]
	public function runExampleMethodAvoidsImportWhenShortNameIsTaken(): void
	{
		$skeleton = $this->skeleton([
			'background_tasks' => [
				0 => [
					'classname'  => 'Integration',
					'regularity' => '',
				],
			],
		]);

		$result = (new Generator())->generate($skeleton, 'Demo', 'demo');

		$this->lint($result['content']);
		$this->assertStringNotContainsString(
			'use Bugo\Demo\Tasks\Integration;',
			$result['content']
		);
		$this->assertStringContainsString(
			"'\\\\' . \Bugo\Demo\Tasks\Integration::class,",
			$result['content']
		);
	}

	private function skeleton(array $overrides = []): array
	{
		return array_merge([
			'name'               => 'Demo Mod',
			'filename'           => 'Class-Demo',
			'hooks'              => ['integrate_admin_areas'],
			'author'             => 'Bugo',
			'namespace'          => 'Bugo',
			'email'              => 'bugo@example.test',
			'readmes'            => [],
			'version'            => '1.0',
			'site'               => 'https://example.test',
			'settings_area'      => 0,
			'license'            => 'mit',
			'license_data'       => ['link' => 'https://x', 'full_name' => 'MIT'],
			'make_dir'           => true,
			'use_lang_dir'       => false,
			'make_template'      => false,
			'make_script'        => false,
			'make_css'           => false,
			'make_readme'        => false,
			'add_copyrights'     => false,
			'min_php_version'    => '',
			'smf_target_version' => '2.1',
			'callbacks'          => [],
			'options'            => [],
			'tables'             => [],
			'scheduled_tasks'    => [],
			'background_tasks'   => [],
			'legacy_tasks'       => [],
			'title'              => ['english' => 'Demo'],
			'description'        => ['english' => 'Desc'],
		], $overrides);
	}

	private function lint(string $code): void
	{
		$file = tempnam(sys_get_temp_dir(), 'smm_') . '.php';

		try {
			file_put_contents($file, $code);

			exec(
				escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1',
				$output,
				$exitCode
			);

			$this->assertSame(
				0,
				$exitCode,
				implode("\n", $output) . "\n---\n" . $code
			);
		} finally {
			@unlink($file);
		}
	}
}
