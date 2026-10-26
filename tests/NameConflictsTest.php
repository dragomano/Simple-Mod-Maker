<?php declare(strict_types=1);

namespace Tests;

use Bugo\SimpleModMaker\Validator;
use PHPUnit\Framework\TestCase;

class NameConflictsTest extends TestCase
{
	private function runValidator(array $post): array
	{
		global $context, $txt;

		$context = [];

		$txt = [
			'smm_error_no_name'                => 'no name',
			'smm_error_no_filename'            => 'no filename',
			'smm_error_no_valid_filename'      => 'bad filename',
			'smm_error_invalid_version'        => 'bad version',
			'smm_error_invalid_data_type'      => 'bad data type',
			'smm_error_invalid_license'        => 'bad license',
			'smm_error_invalid_smf_version'    => 'bad smf version',
			'smm_error_invalid_settings_area'  => 'bad settings area',
			'smm_error_invalid_option_type'    => 'bad option type',
			'smm_error_invalid_column_type'    => 'bad column type',
			'smm_error_invalid_regularities'   => 'bad regularity',
			'smm_error_invalid_identifier'     => 'bad identifier',
			'smm_error_invalid_size'           => 'bad size',
			'smm_error_option_name_too_long'   => 'option too long',
			'smm_error_table_name_too_long'    => 'table too long',
			'smm_error_column_name_too_long'   => 'column too long',
			'smm_error_conflicting_method'     => 'conflicting method',
			'smm_error_conflicting_task_class' => 'conflicting task class',
			'smm_error_reserved_name'          => 'reserved name',
		];

		$_POST    = $post;
		$_REQUEST = $post;

		(new Validator())->validate();

		return $context['post_errors'] ?? [];
	}

	public function testLegacyMethodNamedSettingsIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'settings_area'       => 2,
			'legacy_task_methods' => ['settings'],
		]));

		$this->assertContains('conflicting method', $errors);
	}

	public function testLegacyMethodNamedHooksIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'legacy_task_methods' => ['hooks'],
		]));

		$this->assertContains('conflicting method', $errors);
	}

	public function testLegacyMethodDuplicateIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'legacy_task_methods' => ['runDaily', 'rundaily'],
		]));

		$this->assertContains('conflicting method', $errors);
	}

	public function testLegacyMethodNamedLikeHookMethodIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'hooks'               => ['integrate_load_theme'],
			'legacy_task_methods' => ['loadTheme'],
		]));

		$this->assertContains('conflicting method', $errors);
	}

	public function testHookMethodCollisionAfterTransformationIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'hooks' => ['integrate_credits', 'integratecredits'],
		]));

		$this->assertContains('conflicting method', $errors);
	}

	public function testLegacyMethodNamedLikeTaskRunnerIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'legacy_task_methods'          => ['runCleanupBackground'],
			'background_task_classnames'   => ['CleanupBackground'],
			'background_task_regularities' => [0 => 0],
		]));

		$this->assertContains('conflicting method', $errors);
	}

	public function testReservedScheduledClassNameIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'task_slugs'        => ['match'],
			'task_regularities' => [0 => 1],
		]));

		$this->assertContains('reserved name', $errors);
	}

	public function testReservedBackgroundClassNameIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'background_task_classnames'   => ['Class'],
			'background_task_regularities' => [0 => 0],
		]));

		$this->assertContains('reserved name', $errors);
	}

	public function testDuplicateScheduledTaskClassesAreRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'task_slugs'        => ['demo_task', 'demo_task'],
			'task_regularities' => [0 => 0, 1 => 1],
		]));

		$this->assertContains('conflicting task class', $errors);
	}

	public function testScheduledAndBackgroundWithSameNameAreRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'task_slugs'                   => ['daily'],
			'task_regularities'            => [0 => 0],
			'background_task_classnames'   => ['Daily'],
			'background_task_regularities' => [0 => 0],
		]));

		$this->assertContains('conflicting task class', $errors);
	}

	public function testValidNamesPassWithoutConflictErrors(): void
	{
		$errors = $this->runValidator($this->basePost([
			'settings_area'                => 2,
			'hooks'                        => ['integrate_load_theme'],
			'legacy_task_methods'          => ['runDaily'],
			'legacy_task_regularities'     => [0 => 1],
			'task_slugs'                   => ['demo_task'],
			'task_regularities'            => [0 => 1],
			'background_task_classnames'   => ['CleanupBackground'],
			'background_task_regularities' => [0 => 0],
		]));

		$this->assertNotContains('conflicting method', $errors);
		$this->assertNotContains('conflicting task class', $errors);
		$this->assertNotContains('reserved name', $errors);
	}

	public function testModClassnameShadowingImportIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'smf_target_version' => '3.0',
			'filename'           => 'Config',
		]));

		$this->assertContains('reserved name', $errors);
	}

	public function testModClassnameBesidesTasksOrAnotherTargetPasses(): void
	{
		$errors = $this->runValidator($this->basePost([
			'smf_target_version' => '2.1',
			'filename'           => 'Config',
		]));

		$this->assertNotContains('reserved name', $errors);

		$errors = $this->runValidator($this->basePost([
			'smf_target_version' => '3.0',
			'filename'           => 'Config',
			'make_dir'           => '1',
		]));

		$this->assertNotContains('reserved name', $errors);
	}

	private function basePost(array $overrides): array
	{
		return array_merge([
			'save'     => '',
			'name'     => 'Demo',
			'filename' => 'Demo',
			'version'  => '1.0.0',
		], $overrides);
	}

	protected function tearDown(): void
	{
		$_POST    = [];
		$_REQUEST = [];

		unset($GLOBALS['txt'], $GLOBALS['context']);
	}
}
