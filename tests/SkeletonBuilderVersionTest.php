<?php declare(strict_types=1);

namespace Tests;

use Bugo\SimpleModMaker\SkeletonBuilder;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class SkeletonBuilderVersionTest extends TestCase
{
	public function testVersionIsNormalizedWhenBuildingStructure(): void
	{
		global $txt;

		$txt = [
			'smm_license_own'  => 'Own',
			'smm_license_name' => 'Own License',
			'smm_license_link' => 'https://example.test/license',
		];

		$builder  = new SkeletonBuilder();
		$method   = new ReflectionMethod($builder, 'buildBasicStructure');
		$skeleton = $method->invoke($builder, ['version' => '1.0/../x'], []);

		$this->assertSame("1.0_.._x", $skeleton['version']);
	}

	protected function tearDown(): void
	{
		unset($GLOBALS['txt']);
	}
}