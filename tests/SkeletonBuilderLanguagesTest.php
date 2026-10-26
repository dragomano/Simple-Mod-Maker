<?php declare(strict_types=1);

namespace Tests;

use Bugo\SimpleModMaker\SkeletonBuilder;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

#[CoversMethod(SkeletonBuilder::class, 'processTranslations')]
class SkeletonBuilderLanguagesTest extends TestCase
{
	protected function setUp(): void
	{
		global $context;

		$context['smm_languages'] = [
			['filename' => 'english', 'name' => 'English'],
			['filename' => 'polish', 'name' => 'Polski'],
			['filename' => 'russian', 'name' => 'Русский'],
		];
	}

	public function testLanguageListContainsEnglishAndOnlyFilledLanguages(): void
	{
		$builder = new SkeletonBuilder();
		$method  = new ReflectionMethod($builder, 'processTranslations');

		$skeleton = [
			'options' => [
				0 => [
					'name'         => 'enable_thing',
					'type'         => 'check',
					'translations' => ['russian' => 'Вкл'],
				],
			],
			'scheduled_tasks' => [
				0 => [
					'slug'         => 'cleanup',
					'names'        => ['english' => 'Cleanup'],
					'descriptions' => [],
				],
			],
		];

		$result = $method->getClosure($builder)([
			'title_english' => 'Demo',
			'title_polish'  => '',
		], $skeleton);

		$this->assertSame(['english', 'russian'], $skeleton['languages']);
		$this->assertSame(['english' => 'Demo'], $result['title']);
		$this->assertSame([], $result['description']);
	}

	public function testLanguageListIsEmptyFallbackWithoutFilledStrings(): void
	{
		$builder = new SkeletonBuilder();
		$method  = new ReflectionMethod($builder, 'processTranslations');

		$skeleton = [
			'options'         => [],
			'scheduled_tasks' => [],
		];

		$method->getClosure($builder)([], $skeleton);

		$this->assertSame(['english'], $skeleton['languages']);
	}
}
