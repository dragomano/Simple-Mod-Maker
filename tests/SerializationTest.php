<?php declare(strict_types=1);

namespace Tests;

use Bugo\SimpleModMaker\Builder;
use Bugo\SimpleModMaker\Generator;
use Bugo\SimpleModMaker\Hooks\AutoloadHook;
use Bugo\SimpleModMaker\Hooks\CreditsHook;
use Bugo\SimpleModMaker\Serializer;
use Bugo\SimpleModMaker\Validator;
use FilesystemIterator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionMethod;

/**
 * Regression tests for the separation between validation, PHP/JS serialization
 * and HTML escaping. User text must survive the pipeline verbatim and must never
 * be able to break out of the context it is written into.
 */
#[CoversClass(Serializer::class)]
class SerializationTest extends TestCase
{
	private const HOSTILE = "O'Brien \"quoted\" & <b>tag</b> Привет 😀 trail\\";

	/** @var list<string> */
	private array $tempDirs = [];

	protected function tearDown(): void
	{
		foreach ($this->tempDirs as $dir) {
			self::deleteDirectory($dir);
		}

		$this->tempDirs = [];

		$_POST = $_REQUEST = [];
	}

	#[Test]
	public function validatorKeepsRawValuesInsteadOfHtmlEntities(): void
	{
		global $context, $txt;

		$context = [];
		$txt     = [
			'smm_error_no_name'           => 'no name',
			'smm_error_no_filename'       => 'no filename',
			'smm_error_no_valid_filename' => 'bad filename',
			'smm_error_invalid_version'   => 'bad version',
		];

		$_POST = [
			'save'        => '',
			'name'        => self::HOSTILE,
			'filename'    => 'Class-Demo',
			'version'     => '1.0',
			'description' => "  spaced & 'kept'  ",
		];

		$data = (new Validator())->validate();

		$this->assertSame(self::HOSTILE, $data['name']);
		$this->assertSame("spaced & 'kept'", $data['description']);
		$this->assertStringNotContainsString('&amp;', $data['name']);
		$this->assertStringNotContainsString('&#039;', $data['name']);
	}

	#[Test]
	public function jsonPayloadsCannotBreakScriptOrAttributeContexts(): void
	{
		$value = [
			'hook' => "</script><script>alert('x')</script>\" & \\",
		];

		$json      = Serializer::json($value);
		$attribute = Serializer::jsonAttribute($value);

		$this->assertStringNotContainsString('</script>', $json);
		$this->assertStringNotContainsString('<script>', $json);
		$this->assertStringNotContainsString('"', $attribute);
		$this->assertStringContainsString('\\u003C', $json);
		$this->assertStringContainsString('\\u0022', $json);
		$this->assertStringContainsString('&quot;', $attribute);
		$this->assertSame($value, json_decode(html_entity_decode($attribute, ENT_QUOTES), true));
	}

	#[Test]
	public function languageFilesRoundTripHostileValues(): void
	{
		$base = $this->makeTempDir();

		$skeleton = $this->skeleton([
			'title'       => ['english' => self::HOSTILE],
			'description' => ['english' => "Back\\slash 'quoted' & done"],
			'options'     => [
				0 => [
					'name'         => 'enable',
					'type'         => 'check',
					'default'      => true,
					'variants'     => '',
					'translations' => ['english' => self::HOSTILE],
				],
				1 => [
					'name'         => 'choice',
					'type'         => 'select',
					'default'      => '',
					'variants'     => "a'b|c\"d|&e",
					'translations' => ['english' => 'Choice'],
				],
			],
		]);

		$builder = new Builder([
			'skeleton'  => $skeleton,
			'classname' => 'Demo',
			'snakename' => 'demo',
			'path'      => $base . '/demo',
		]);

		$builder->create('<?php');
		package_flush_cache();

		$langFile = $base . '/demo/Themes/default/languages/Demo.english.php';
		$this->assertFileExists($langFile);

		$this->lintFile($langFile);

		$txt = [];
		include $langFile;

		$this->assertSame(self::HOSTILE, $txt['demo_title']);
		$this->assertSame("Back\\slash 'quoted' & done", $txt['demo_description']);
		$this->assertSame(self::HOSTILE, $txt['demo_enable']);
		$this->assertSame(["a'b", 'c"d', '&e'], $txt['demo_choice_set']);
	}

	#[Test]
	public function databaseFileRoundTripsHostileColumnValues(): void
	{
		$base = $this->makeTempDir();

		$skeleton = $this->skeleton([
			'tables' => [
				0 => [
					'name'    => 'demo_table',
					'columns' => [
						0 => [
							'name'    => 'id',
							'type'    => 'int',
							'null'    => false,
							'size'    => 10,
							'auto'    => true,
							'default' => '',
						],
						1 => [
							'name'    => 'note',
							'type'    => 'varchar',
							'null'    => false,
							'size'    => 255,
							'auto'    => false,
							'default' => "O'Brien \\",
						],
					],
				],
			],
		]);

		$builder = new Builder([
			'skeleton'  => $skeleton,
			'classname' => 'Demo',
			'snakename' => 'demo',
			'path'      => $base . '/demo',
		]);

		$builder->create('<?php');
		package_flush_cache();

		$databaseFile = $base . '/demo/database.php';
		$this->assertFileExists($databaseFile);

		$this->lintFile($databaseFile);

		$database = file_get_contents($databaseFile);

		$this->assertStringContainsString("'default' => 'O\\'Brien \\\\',", $database);
	}

	#[Test]
	public function generatedDocblocksCannotBeTerminatedByUserText(): void
	{
		$skeleton = $this->skeleton([
			'name' => 'Evil */ phpinfo(); /*',
			'site' => 'https://example.test/ */ exit; /*',
		]);

		$result = (new Generator())->generate($skeleton, 'Demo', 'demo');

		$this->lint($result['content']);
		$this->assertStringNotContainsString('*/ phpinfo(); /*', $result['content']);
		$this->assertStringContainsString('* / phpinfo(); /*', $result['content']);
		$this->assertStringContainsString('https://example.test/ * / exit; /*', $result['content']);
	}

	#[Test]
	public function creditsHookKeepsAuthorAndNameInsideAStringLiteral(): void
	{
		$hook = new CreditsHook([
			'smm_skeleton' => [
				'name'               => self::HOSTILE,
				'author'             => "O'Brien \\",
				'smf_target_version' => '2.1',
			],
		], 'Demo', 'demo');

		$body = implode("\n", $hook->getBody());

		$this->lint("<?php\nfunction creditsDemo()\n{\n\tglobal \$context;\n\t$body\n}\n");
	}

	#[Test]
	public function autoloadHookKeepsNamespaceInsideAStringLiteral(): void
	{
		$hook = new AutoloadHook([
			'smm_skeleton' => [
				'namespace' => "O'Brien \\",
				'make_dir'  => true,
			],
		], 'Demo', 'demo');

		$body = implode("\n", $hook->getBody());

		$this->lint("<?php\nfunction autoloadDemo(array &\$classMap)\n{\n\t$body\n}\n");
	}

	#[Test]
	public function jsonPayloadCannotCloseTheScriptElement(): void
	{
		$payload = Serializer::json(['value' => '</script><img src=x onerror=alert(1)>']);

		$this->assertStringNotContainsString('</script>', $payload);
		$this->assertStringNotContainsString('<img', $payload);
		$this->assertStringContainsString('\u003C', $payload);
		$this->assertStringContainsString('script\u003E', $payload);

		$this->assertSame(
			['value' => '</script><img src=x onerror=alert(1)>'],
			json_decode($payload, true)
		);
	}

	#[Test]
	public function packageInfoUsesEscapedTextNodes(): void
	{
		$base = $this->makeTempDir();

		$skeleton = $this->skeleton([
			'name'    => 'Demo & <b>bold</b>',
			'version' => '1.0',
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

		$packageInfo = file_get_contents($base . '/demo/package-info.xml');

		$this->assertStringContainsString('Demo &amp; &lt;b&gt;bold&lt;/b&gt;', $packageInfo);
		$this->assertStringNotContainsString('<b>bold</b>', $packageInfo);
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

	private function makeTempDir(): string
	{
		$dir = sys_get_temp_dir() . '/smm_serialization_' . bin2hex(random_bytes(4));

		mkdir($dir, 0777, true);

		$this->tempDirs[] = $dir;

		return $dir;
	}

	private function lint(string $code): void
	{
		$file = $this->writeTempPhp($code);

		try {
			$output   = [];
			$exitCode = 0;

			exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $exitCode);

			$this->assertSame(
				0,
				$exitCode,
				implode("\n", $output) . "\n---\n" . $code
			);
		} finally {
			@unlink($file);
		}
	}

	private function lintFile(string $file): void
	{
		$this->lint(file_get_contents($file));
	}

	private function writeTempPhp(string $code): string
	{
		$file = tempnam(sys_get_temp_dir(), 'smm_') . '.php';

		file_put_contents($file, $code);

		return $file;
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
