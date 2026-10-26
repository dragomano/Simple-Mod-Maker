<?php declare(strict_types=1);

namespace Tests;

use Bugo\SimpleModMaker\Validator;
use PHPUnit\Framework\TestCase;

class ValidatorVersionTest extends TestCase
{
	private function runValidator(array $post): array
	{
		global $context, $txt;

		$context = [];

		$txt = [
			'smm_error_no_name'           => 'no name',
			'smm_error_no_filename'       => 'no filename',
			'smm_error_no_valid_filename' => 'bad filename',
			'smm_error_invalid_version'   => 'bad version',
		];

		$_POST    = $post;
		$_REQUEST = $post;

		(new Validator())->validate();

		return $context['post_errors'] ?? [];
	}

	public function testUnsafeVersionIsRejected(): void
	{
		$post = [
			'save'     => '',
			'name'     => 'Demo',
			'filename' => 'Class-Demo',
			'version'  => "1.0/../x\n",
		];

		$errors = $this->runValidator($post);

		$this->assertContains('bad version', $errors);
	}

	public function testSafeVersionIsAccepted(): void
	{
		$post = [
			'save'     => '',
			'name'     => 'Demo',
			'filename' => 'Class-Demo',
			'version'  => '1.0.0',
		];

		$errors = $this->runValidator($post);

		$this->assertNotContains('bad version', $errors);
	}

	protected function tearDown(): void
	{
		$_POST    = [];
		$_REQUEST = [];
	}
}