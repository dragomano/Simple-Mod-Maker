<?php declare(strict_types=1);

namespace Tests;

use Bugo\SimpleModMaker\Validator;
use PHPUnit\Framework\TestCase;

class ValidatorSchemaTest extends TestCase
{
	private function runValidator(array $post): array
	{
		global $context, $txt;

		$context = [];

		$txt = [
			'smm_error_no_name'                   => 'no name',
			'smm_error_no_filename'               => 'no filename',
			'smm_error_no_valid_filename'         => 'bad filename',
			'smm_error_invalid_version'           => 'bad version',
			'smm_error_invalid_data_type'         => 'bad data type',
			'smm_error_invalid_license'           => 'bad license',
			'smm_error_invalid_smf_version'       => 'bad smf version',
			'smm_error_invalid_settings_area'     => 'bad settings area',
			'smm_error_invalid_option_type'       => 'bad option type',
			'smm_error_invalid_column_type'       => 'bad column type',
			'smm_error_invalid_regularities'      => 'bad regularity',
			'smm_error_invalid_identifier'        => 'bad identifier',
			'smm_error_invalid_size'              => 'bad size',
			'smm_error_invalid_column_attributes' => 'bad column attributes',
			'smm_error_invalid_default'           => 'bad default',
			'smm_error_empty_columns'             => 'empty columns',
			'smm_error_duplicate_columns'         => 'duplicate columns',
			'smm_error_multiple_auto_columns'     => 'multiple auto columns',
			'smm_error_option_name_too_long'      => 'option too long',
			'smm_error_table_name_too_long'       => 'table too long',
			'smm_error_column_name_too_long'      => 'column too long',
		];

		$_POST    = $post;
		$_REQUEST = $post;

		(new Validator())->validate();

		return $context['post_errors'] ?? [];
	}

	public function testArrayInsteadOfScalarNameIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost(['name' => ['x']]));

		$this->assertContains('bad data type', $errors);
	}

	public function testScalarInsteadOfHooksIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost(['hooks' => 'integrate_load_theme']));

		$this->assertContains('bad data type', $errors);
	}

	public function testUnknownLicenseIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost(['license' => 'wtfpl']));

		$this->assertContains('bad license', $errors);
	}

	public function testKnownLicenseIsAccepted(): void
	{
		$errors = $this->runValidator($this->basePost(['license' => 'mit']));

		$this->assertNotContains('bad license', $errors);
	}

	public function testUnknownTargetVersionIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost(['smf_target_version' => '1.1']));

		$this->assertContains('bad smf version', $errors);
	}

	public function testUnknownSettingsAreaIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost(['settings_area' => 9]));

		$this->assertContains('bad settings area', $errors);
	}

	public function testUnknownOptionTypeIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'option_names' => ['demo_option'],
			'option_types' => ['nonsense'],
		]));

		$this->assertContains('bad option type', $errors);
	}

	public function testMissingOptionTypeIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'option_names' => ['demo_option'],
		]));

		$this->assertContains('bad option type', $errors);
	}

	public function testUnknownColumnTypeIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'table_names'  => ['demo_table'],
			'column_names' => [0 => ['id']],
			'column_types' => [0 => ['bigint']],
		]));

		$this->assertContains('bad column type', $errors);
	}

	public function testMissingColumnTypeIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'table_names'  => ['demo_table'],
			'column_names' => [0 => ['id']],
		]));

		$this->assertContains('bad column type', $errors);
	}

	public function testBadIdentifierIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'table_names'  => ['Demo-Table'],
			'column_names' => [0 => ['id']],
			'column_types' => [0 => ['int']],
		]));

		$this->assertContains('bad identifier', $errors);
	}

	public function testBadTaskSlugAndRegularityAreRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'task_slugs'        => ['Bad Slug'],
			'task_regularities' => [0 => 5],
		]));

		$this->assertContains('bad identifier', $errors);
		$this->assertContains('bad regularity', $errors);
	}

	public function testBadBackgroundTaskIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'background_task_classnames'   => ['lowercase_start'],
			'background_task_regularities' => [0 => 0],
		]));

		$this->assertContains('bad identifier', $errors);
	}

	public function testBadLegacyTaskRegularityIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'legacy_task_methods'      => ['runDemo'],
			'legacy_task_regularities' => [0 => 2],
		]));

		$this->assertContains('bad regularity', $errors);
	}

	public function testBadColumnSizeIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'table_names'  => ['demo_table'],
			'column_names' => [0 => ['title']],
			'column_types' => [0 => ['varchar']],
			'column_sizes' => [0 => ['oops']],
		]));

		$this->assertContains('bad size', $errors);
	}

	public function testZeroColumnSizeIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'table_names'  => ['demo_table'],
			'column_names' => [0 => ['title']],
			'column_types' => [0 => ['varchar']],
			'column_sizes' => [0 => [0]],
		]));

		$this->assertContains('bad size', $errors);
	}

	public function testTableWithoutColumnsIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'table_names' => ['demo_table'],
		]));

		$this->assertContains('empty columns', $errors);
	}

	public function testTableWithOnlyBlankColumnsIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'table_names'  => ['demo_table'],
			'column_names' => [0 => ['', '']],
		]));

		$this->assertContains('empty columns', $errors);
	}

	public function testDuplicateColumnNamesAreRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'table_names'  => ['demo_table'],
			'column_names' => [0 => ['title', 'title']],
			'column_types' => [0 => ['varchar', 'varchar']],
			'column_sizes' => [0 => [255, 255]],
		]));

		$this->assertContains('duplicate columns', $errors);
	}

	public function testTwoAutoIncrementColumnsInATableAreRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'table_names'  => ['demo_table'],
			'column_names' => [0 => ['id', 'counter']],
			'column_types' => [0 => ['int', 'int']],
			'column_auto'  => [0 => [0 => 'on', 1 => 'on']],
			'column_sizes' => [0 => [10, 10]],
		]));

		$this->assertContains('multiple auto columns', $errors);
	}

	public function testAutoIncrementFlagOnNonIntegerColumnIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'table_names'  => ['demo_table'],
			'column_names' => [0 => ['title']],
			'column_types' => [0 => ['varchar']],
			'column_sizes' => [0 => [255]],
			'column_auto'  => [0 => [0 => 'on']],
		]));

		$this->assertContains('bad column attributes', $errors);
	}

	public function testNullFlagOnNonTextColumnIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'table_names'  => ['demo_table'],
			'column_names' => [0 => ['id']],
			'column_types' => [0 => ['int']],
			'column_sizes' => [0 => [10]],
			'column_null'  => [0 => [0 => 'on']],
		]));

		$this->assertContains('bad column attributes', $errors);
	}

	public function testSizeOnTextColumnIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'table_names'  => ['demo_table'],
			'column_names' => [0 => ['notes']],
			'column_types' => [0 => ['text']],
			'column_sizes' => [0 => [255]],
		]));

		$this->assertContains('bad column attributes', $errors);
	}

	public function testNonNumericDefaultForIntegerColumnIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'table_names'     => ['demo_table'],
			'column_names'    => [0 => ['id']],
			'column_types'    => [0 => ['int']],
			'column_sizes'    => [0 => [10]],
			'column_defaults' => [0 => ['abc']],
		]));

		$this->assertContains('bad default', $errors);
	}

	public function testDefaultForAutoIncrementOrTextColumnIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'table_names'     => ['demo_table'],
			'column_names'    => [0 => ['id', 'notes']],
			'column_types'    => [0 => ['int', 'text']],
			'column_sizes'    => [0 => [10]],
			'column_auto'     => [0 => [0 => 'on']],
			'column_defaults' => [0 => [0 => '1', 1 => 'x']],
		]));

		$this->assertContains('bad column attributes', $errors);
	}

	public function testBadHookNameIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'hooks' => ['integrate_load_theme"><script>'],
		]));

		$this->assertContains('bad data type', $errors);
	}

	public function testNestedArrayInDefaultsIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'option_names'    => ['demo_option'],
			'option_types'    => ['text'],
			'option_defaults' => [['nested']],
		]));

		$this->assertContains('bad data type', $errors);
	}

	public function testBadMinPhpVersionIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost(['min_php_version' => 'eight']));

		$this->assertContains('bad data type', $errors);
	}

	public function testValidCompleteFormPasses(): void
	{
		$errors = $this->runValidator($this->basePost([
			'smf_target_version' => '3.0',
			'settings_area'      => 2,
			'hooks'              => ['integrate_load_theme'],
			'option_names'       => ['demo_option'],
			'option_types'       => ['select'],
			'option_defaults'    => ['first'],
			'option_variants'    => ['first|second'],
			'table_names'        => ['demo_table'],
			'column_names'       => [0 => ['id', 'title', 'notes']],
			'column_types'       => [0 => ['int', 'varchar', 'mediumtext']],
			'column_sizes'       => [0 => [10, 255]],
			'column_auto'        => [0 => [0 => 'on']],
			'column_null'        => [0 => [2 => 'on']],
			'task_slugs'         => ['demo_task'],
			'task_regularities'  => [0 => 1],
		]));

		$this->assertSame([], $errors);
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
	}
}
