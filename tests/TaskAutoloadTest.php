<?php declare(strict_types=1);

namespace Tests;

use Bugo\SimpleModMaker\Generator;
use Bugo\SimpleModMaker\Hook;
use Bugo\SimpleModMaker\Hooks\HookFactory;
use Bugo\SimpleModMaker\SkeletonBuilder;
use FilesystemIterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionMethod;

#[CoversClass(SkeletonBuilder::class)]
#[CoversClass(HookFactory::class)]
#[CoversMethod(HookFactory::class, 'create')]
class TaskAutoloadTest extends TestCase
{
	#[Test]
	public function determineHooksAddsAutoloadHookForTaskClasses(): void
	{
		$builder = new SkeletonBuilder();
		$method  = new ReflectionMethod($builder, 'determineHooks');

		$base = [
			'hooks'            => [],
			'settings_area'    => 0,
			'add_copyrights'   => false,
			'legacy_tasks'     => [],
			'scheduled_tasks'  => [],
			'background_tasks' => [],
		];

		$this->assertNotContains(Hook::AUTOLOAD, $method->invoke($builder, $base));

		foreach (['scheduled_tasks', 'background_tasks'] as $key) {
			$skeleton       = $base;
			$skeleton[$key] = [0 => $key === 'scheduled_tasks'
				? ['slug' => 'daily_cleanup', 'names' => [], 'descriptions' => [], 'regularity' => 0]
				: ['classname' => 'BackgroundTask', 'regularity' => '']];

			$this->assertContains(Hook::AUTOLOAD, $method->invoke($builder, $skeleton));
		}

		$skeleton                    = $base;
		$skeleton['hooks']           = [Hook::AUTOLOAD];
		$skeleton['scheduled_tasks'] = [0 => ['slug' => 'daily_cleanup', 'names' => [], 'descriptions' => [], 'regularity' => 0]];

		$hooks = $method->invoke($builder, $skeleton);

		$this->assertCount(1, array_keys($hooks, Hook::AUTOLOAD, true));
	}

	#[Test]
	public function taskFqcnLoadsViaGeneratedClassMapInCleanProcess(): void
	{
		foreach (['2.1', '3.0'] as $targetVersion) {
			$skeleton  = $this->skeleton(['smf_target_version' => $targetVersion]);
			$generator = new Generator();
			$result    = $generator->generate($skeleton, 'Demo', 'demo');

			$root      = sys_get_temp_dir() . '/smm_autoload_' . bin2hex(random_bytes(4));
			$sourcedir = $root . '/Sources';

			try {
				foreach ($result['tasks'] as $task) {
					$filepath = $sourcedir . '/Demo/Tasks/' . $task['filename'];

					if (! is_dir(dirname($filepath))) {
						mkdir(dirname($filepath), 0777, true);
					}

					file_put_contents($filepath, $task['content']);
				}

				$hook = HookFactory::create(Hook::AUTOLOAD, ['smm_skeleton' => $skeleton], 'Demo', 'demo');

				$this->assertNotNull($hook);

				$expectedLine = "\$classMap[" . var_export('Bugo\Demo\\', true) . "] = " . var_export('Demo/', true) . ';';

				$this->assertSame([$expectedLine], $hook->getBody());

				$callables = [
					$skeleton['scheduled_tasks'][0]['callable'],
					$skeleton['background_tasks'][0]['callable'],
				];

				$targets = array_map(
					fn(string $callable): string => ltrim(explode('::', str_replace('\\\\', '\\', $callable), 2)[0], '\\'),
					$callables
				);

				$output   = [];
				$exitCode = 1;
				$script   = '';

				$this->execAutoloadScript($script, $sourcedir, $targetVersion === '2.1', $hook->getBody(), $targets);

				$file = tempnam(sys_get_temp_dir(), 'smm_autoload_') . '.php';

				try {
					file_put_contents($file, $script);

					exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1', $output, $exitCode);
				} finally {
					@unlink($file);
				}

				$this->assertSame(0, $exitCode, implode("\n", $output) . "\n---\n" . $script);

				$payload = json_decode($output[0] ?? '', true);

				$this->assertIsArray($payload);
				$this->assertSame(array_fill_keys($targets, true), $payload['results']);

				foreach ($targets as $fqcn) {
					$this->assertArrayHasKey($fqcn, $payload['loaded']);

					$leaf = substr($fqcn, (int) strrpos($fqcn, '\\') + 1) . '.php';

					$this->assertSame($sourcedir . '/Demo/Tasks/' . $leaf, $payload['loaded'][$fqcn]);
				}
			} finally {
				self::deleteDirectory($root);
			}
		}
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
			'scheduled_tasks'    => [
				0 => [
					'slug'         => 'daily_cleanup',
					'names'        => ['english' => 'Daily cleanup'],
					'descriptions' => ['english' => 'Cleans stale data'],
					'regularity'   => 0,
				],
			],
			'background_tasks'   => [
				0 => [
					'classname'  => 'CleanupBackground',
					'regularity' => '',
				],
			],
			'legacy_tasks'       => [],
			'title'              => ['english' => 'Demo'],
			'description'        => ['english' => 'Desc'],
		], $overrides);
	}

	private function execAutoloadScript(string &$script, string $sourcedir, bool $legacyParents, array $classMapBody, array $targets): void
	{
		$script = "<?php declare(strict_types=1);\n\n";

		if ($legacyParents) {
			$script .= "class SMF_BackgroundTask {}\n\n";
		} else {
			$script .= "namespace SMF\\Tasks {\n\tclass ScheduledTask {}\n\tclass BackgroundTask {}\n}\n\nnamespace {\n";
		}

		$script .= "\$sourcedir = " . var_export($sourcedir, true) . ";\n\n";
		$script .= "\$classMap = [];\n\n" . implode("\n", $classMapBody) . "\n\n";
		$script .= "\$loaded = [];\n\n";

		$script .= "spl_autoload_register(function (\$class) use (\$sourcedir, \$classMap, &\$loaded) {\n";
		$script .= "\tforeach (\$classMap as \$prefix => \$dirname) {\n";
		$script .= "\t\t\$length = strlen(\$prefix);\n";
		$script .= "\t\tif (strncmp(\$prefix, \$class, \$length) !== 0) {\n\t\t\tcontinue;\n\t\t}\n";
		$script .= "\t\t\$filename = \$dirname . strtr(substr(\$class, \$length), '\\\\', '/') . '.php';\n";
		$script .= "\t\tif (is_file(\$sourcedir . '/' . \$filename)) {\n";
		$script .= "\t\t\t\$loaded[\$class] = \$sourcedir . '/' . \$filename;\n";
		$script .= "\t\t\trequire \$sourcedir . '/' . \$filename;\n\t\t\treturn;\n\t\t}\n\t}\n});\n\n";
		$script .= "\$targets = " . var_export($targets, true) . ";\n";
		$script .= "\$results = [];\n";
		$script .= "foreach (\$targets as \$fqcn) {\n\t\$results[\$fqcn] = class_exists(\$fqcn, true);\n}\n";
		$script .= "echo json_encode(['results' => \$results, 'loaded' => \$loaded]);\n";

		if (! $legacyParents) {
			$script .= "}\n";
		}
	}

	private static function deleteDirectory(string $path): void
	{
		if (! is_dir($path)) {
			return;
		}

		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ($items as $item) {
			$item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
		}

		@rmdir($path);
	}
}
