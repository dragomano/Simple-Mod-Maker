<?php declare(strict_types=1);

namespace Tests;

use Bugo\SimpleModMaker\Builder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BuilderPathGuardTest extends TestCase
{
	public function testCreateRejectsPathOutsideAllowedBase(): void
	{
		$allowed = sys_get_temp_dir() . '/smm_allowed_' . bin2hex(random_bytes(4));
		$outside = sys_get_temp_dir() . '/smm_outside_' . bin2hex(random_bytes(4));

		mkdir($allowed, 0777, true);

		try {
			$builder = new Builder([
				'skeleton'     => ['version' => '1.0'],
				'classname'    => 'Demo',
				'snakename'    => 'demo',
				'path'         => $outside,
				'allowed_base' => $allowed,
			]);

			$this->expectException(InvalidArgumentException::class);

			$builder->create('<?php');
		} finally {
			@rmdir($allowed);
			@rmdir($outside);
		}
	}

	protected function tearDown(): void
	{
		// no-op
	}
}