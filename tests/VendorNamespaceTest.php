<?php declare(strict_types=1);

namespace Tests;

use Bugo\SimpleModMaker\Builder;
use Bugo\SimpleModMaker\Generator;
use Bugo\SimpleModMaker\Handler;
use Bugo\SimpleModMaker\SkeletonBuilder;
use Bugo\SimpleModMaker\Validator;
use FilesystemIterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionMethod;

/**
 * The author name is metadata for the license block and the readme, while the
 * generated code lives in the vendor namespace from its own field. A display
 * name with spaces or non-Latin characters must not leak into the code.
 */
#[CoversClass(SkeletonBuilder::class)]
#[CoversClass(Validator::class)]
class VendorNamespaceTest extends TestCase
{
	private const AUTHOR = 'Иван Петров';

	private const NAMESPACE_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$/';

	#[Test]
	public function authorWithSpacesDoesNotBreakGeneration(): void
	{
		$skeleton = $this->buildBasicStructure([], ['smm_mod_author' => self::AUTHOR]);

		$this->assertSame(self::AUTHOR, $skeleton['author']);
		$this->assertMatchesRegularExpression(self::NAMESPACE_PATTERN, $skeleton['namespace']);

		$result = (new Generator())->generate($skeleton, 'Demo', 'demo');

		$this->lint($result['content']);
		$this->assertStringContainsString('@author ' . self::AUTHOR, $result['content']);
		$this->assertStringContainsString('namespace ' . $skeleton['namespace'] . ';', $result['content']);
		$this->assertStringNotContainsString(self::AUTHOR, $skeleton['namespace']);
	}

	#[Test]
	public function submittedNamespaceWinsOverTheStoredVendor(): void
	{
		$skeleton = $this->buildBasicStructure(
			['namespace' => 'MyVendor\\MyMods'],
			['smm_mod_author' => self::AUTHOR, 'smm_mod_namespace' => 'Stored']
		);

		$this->assertSame('MyVendor\\MyMods', $skeleton['namespace']);
		$this->assertSame(self::AUTHOR, $skeleton['author']);
	}

	#[Test]
	public function storedVendorIsUsedWhenTheFormDoesNotSendOne(): void
	{
		$skeleton = $this->buildBasicStructure([], [
			'smm_mod_author'    => self::AUTHOR,
			'smm_mod_namespace' => 'MyVendor',
		]);

		$this->assertSame('MyVendor', $skeleton['namespace']);
	}

	#[Test]
	public function latinPartOfTheAuthorNameBecomesTheVendor(): void
	{
		$skeleton = $this->buildBasicStructure([], ['smm_mod_author' => 'John Doe']);

		$this->assertSame('JohnDoe', $skeleton['namespace']);
		$this->assertSame('John Doe', $skeleton['author']);
	}

	#[Test]
	public function namespaceWithoutLatinCharactersFallsBackToTheDefaultVendor(): void
	{
		$skeleton = $this->buildBasicStructure([], ['smm_mod_author' => self::AUTHOR]);

		$this->assertSame(SMM_VENDOR_DEFAULT, $skeleton['namespace']);
	}

	#[Test]
	public function validatorRejectsInvalidNamespaces(): void
	{
		$invalid = [
			'Иван Петров',
			'My Mods',
			'MyMods\\',
			'\\MyMods',
			'MyMods\\\\Sub',
			'MyMods\\2Fast',
			'MyMods-Other',
		];

		foreach ($invalid as $namespace) {
			$errors = $this->validate(['namespace' => $namespace]);

			$this->assertContains('invalid_namespace', $errors, $namespace);
		}
	}

	#[Test]
	public function validatorAcceptsValidNamespacesAndTheirAbsence(): void
	{
		foreach (['MyMods', 'MyVendor\\MyMods', '_vendor2'] as $namespace) {
			$errors = $this->validate(['namespace' => $namespace]);

			$this->assertNotContains('invalid_namespace', $errors, $namespace);
			$this->assertNotContains('invalid_data_type', $errors, $namespace);
		}

		$errors = $this->validate([]);

		$this->assertNotContains('invalid_namespace', $errors);

		$errors = $this->validate(['namespace' => '']);

		$this->assertNotContains('invalid_namespace', $errors);

		$errors = $this->validate(['namespace' => ['MyMods']]);

		$this->assertContains('invalid_data_type', $errors);
	}

	#[Test]
	public function tasksUseTheNamespaceWhileTheAuthorStaysInTheMetadata(): void
	{
		$skeleton = $this->skeleton([
			'make_dir'        => true,
			'namespace'       => 'MyVendor\\MyMods',
			'author'          => self::AUTHOR,
			'scheduled_tasks' => [
				0 => [
					'slug'         => 'daily_cleanup',
					'names'        => ['english' => 'Daily cleanup'],
					'descriptions' => ['english' => 'Cleans stale data'],
					'regularity'   => 0,
				],
			],
		]);

		$result = (new Generator())->generate($skeleton, 'Demo', 'demo');

		$task = array_values($result['tasks'])[0];

		$this->lint($task['content']);
		$this->lint($result['content']);

		$this->assertStringContainsString('namespace MyVendor\\MyMods\\Demo\\Tasks;', $task['content']);
		$this->assertStringContainsString('namespace MyVendor\\MyMods\\Demo;', $result['content']);
		$this->assertSame(
			'\\\\MyVendor\\\\MyMods\\\\Demo\\\\Tasks\\\\DailyCleanup::execute',
			$skeleton['scheduled_tasks'][0]['callable']
		);
		$this->assertStringContainsString('@author ' . self::AUTHOR, $result['content']);
	}

	#[Test]
	public function packageInfoHooksUseTheNamespaceWhileTheIdKeepsTheAuthor(): void
	{
		$base = sys_get_temp_dir() . '/smm_vendor_' . bin2hex(random_bytes(4));
		mkdir($base, 0777, true);

		try {
			$skeleton = $this->skeleton([
				'namespace' => 'MyVendor\\MyMods',
				'author'    => self::AUTHOR,
			]);

			$builder = new Builder([
				'skeleton'  => $skeleton,
				'classname' => 'Demo',
				'snakename' => 'demo',
				'path'      => $base . '/demo',
			]);

			$builder->create('<?php');

			$method = new ReflectionMethod($builder, 'preparePackageInfo');
			$method->invoke($builder);

			package_flush_cache();

			$packageInfo = html_entity_decode(
				(string) file_get_contents($base . '/demo/package-info.xml'),
				ENT_QUOTES | ENT_XML1,
				'UTF-8'
			);

			$this->assertStringContainsString('<id>' . self::AUTHOR . ':Demo</id>', $packageInfo);
			$this->assertStringContainsString('function="MyVendor\\MyMods\\Demo::hooks#"', $packageInfo);
			$this->assertStringNotContainsString('function="' . self::AUTHOR, $packageInfo);
		} finally {
			self::deleteDirectory($base);
		}
	}

	#[Test]
	public function generatedPackageWithACustomVendorInstallsCleanly(): void
	{
		$base = sys_get_temp_dir() . '/smm_vendor_' . bin2hex(random_bytes(4));
		mkdir($base, 0777, true);

		try {
			$skeleton = $this->skeleton([
				'namespace' => 'MyVendor\\MyMods',
				'author'    => self::AUTHOR,
				'options'   => [
					0 => [
						'name'         => 'enable_feature',
						'type'         => 'check',
						'default'      => true,
						'variants'     => '',
						'translations' => ['english' => 'Enable feature'],
					],
				],
			]);

			$result = (new Generator())->generate($skeleton, 'Demo', 'demo');

			$builder = new Builder([
				'skeleton'  => $skeleton,
				'classname' => 'Demo',
				'snakename' => 'demo',
				'path'      => $base . '/demo',
			]);

			$builder->create($result['content'])->createTasks($result['tasks']);

			$this->lint((string) file_get_contents($base . '/demo/Sources/Class-Demo.php'));
		} finally {
			self::deleteDirectory($base);
		}
	}

	#[Test]
	public function formFieldCarriesThePatternAndTheStoredVendor(): void
	{
		$txt = array_fill_keys([
			'smm_name', 'smm_filename', 'smm_filename_subtext', 'smm_namespace', 'smm_namespace_subtext',
			'smm_hooks', 'smm_hooks_subtext', 'smm_mod_version', 'website', 'smm_site_subtext',
			'smm_license', 'smm_license_own', 'smm_license_name', 'smm_license_link',
			'smm_min_php_version', 'smm_smf_target_version',
		], 'label');

		$handler = new Handler();
		$method  = new ReflectionMethod($handler, 'buildBasicFields');

		$fields = $method->invoke($handler, $this->skeleton(['namespace' => 'MyVendor\\MyMods']), $txt);
		$input  = $fields['namespace']['input'];

		$this->assertSame('MyVendor\\MyMods', $input['attributes']['value']);
		$this->assertSame(Validator::NAMESPACE_PATTERN, $input['attributes']['pattern']);
		$this->assertTrue($input['attributes']['required']);
		$this->assertSame('basic', $input['tab']);
	}

	private function buildBasicStructure(array $postData, array $modSettings): array
	{
		global $txt;

		$txt = [
			'smm_license_own'  => 'Own',
			'smm_license_name' => 'Own License',
			'smm_license_link' => 'https://example.test/license',
		];

		$builder = new SkeletonBuilder();
		$method  = new ReflectionMethod($builder, 'buildBasicStructure');

		return $method->invoke($builder, $postData, $modSettings);
	}

	private function validate(array $post): array
	{
		global $context, $txt;

		$context = [];
		$txt     = [];

		$_POST    = array_merge(['save' => '', 'name' => 'Demo', 'filename' => 'Class-Demo', 'version' => '1.0'], $post);
		$_REQUEST = $_POST;

		(new Validator())->validate();

		return $context['post_errors'] ?? [];
	}

	private function skeleton(array $overrides = []): array
	{
		return array_merge([
			'name'               => 'Demo Mod',
			'filename'           => 'Class-Demo',
			'hooks'              => [],
			'author'             => 'Bugo',
			'namespace'          => 'Bugo',
			'email'              => 'bugo@example.test',
			'readmes'            => ['english' => 'Text'],
			'version'            => '1.0',
			'site'               => 'https://example.test',
			'settings_area'      => 0,
			'license'            => 'mit',
			'license_data'       => ['link' => 'https://x', 'full_name' => 'MIT'],
			'make_dir'           => false,
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
			'languages'          => ['english'],
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

	protected function tearDown(): void
	{
		$_POST    = [];
		$_REQUEST = [];

		unset($GLOBALS['txt'], $GLOBALS['context']);
	}
}
