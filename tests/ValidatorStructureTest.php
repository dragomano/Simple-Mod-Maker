<?php declare(strict_types=1);

namespace Tests;

use Bugo\SimpleModMaker\Validator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The defensive half of the schema: shape violations of the input arrays and
 * of the scalar fields. The form can be posted by hand, so a value of an
 * unexpected type must be reported instead of crashing the generator.
 */
class ValidatorStructureTest extends TestCase
{
	#[Test]
	public function missingNameAndFilenameAreReported(): void
	{
		$errors = $this->runValidator(['save' => '', 'name' => '', 'filename' => '', 'version' => '1.0']);

		$this->assertContains('no name', $errors);
		$this->assertContains('no filename', $errors);
	}

	#[Test]
	public function nonStringFilenameIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost(['filename' => ['Demo']]));

		$this->assertContains('bad data type', $errors);
	}

	#[Test]
	public function oversizedScalarFieldsAreRejected(): void
	{
		$tooLong = str_repeat('1', 256);

		foreach (['name', 'version', 'site', 'min_php_version'] as $field) {
			$errors = $this->runValidator($this->basePost([$field => $tooLong]));

			$this->assertContains('bad data type', $errors, $field);
		}
	}

	#[Test]
	public function oversizedOptionNameIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'option_names' => [str_repeat('a', 31)],
			'option_types' => ['text'],
		]));

		$this->assertContains('option too long', $errors);
	}

	#[Test]
	public function oversizedTableNameIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'table_names' => [str_repeat('a', 65)],
		]));

		$this->assertContains('table too long', $errors);
	}

	#[Test]
	public function oversizedColumnNameIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'table_names'  => ['demo_table'],
			'column_names' => [0 => [str_repeat('a', 65)]],
			'column_types' => [0 => ['varchar']],
			'column_sizes' => [0 => [255]],
		]));

		$this->assertContains('column too long', $errors);
	}

	#[Test]
	public function emptyLicenseIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost(['license' => '']));

		$this->assertContains('bad license', $errors);
	}

	#[Test]
	public function nonScalarFlagFieldsAreRejected(): void
	{
		$fields = ['make_dir', 'use_lang_dir', 'make_template', 'make_script', 'make_css', 'make_readme', 'add_copyrights'];

		foreach ($fields as $field) {
			$errors = $this->runValidator($this->basePost([$field => ['on']]));

			$this->assertContains('bad data type', $errors, $field);
		}
	}

	#[Test]
	public function scalarOptionNamesFieldIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost(['option_names' => 0]));

		$this->assertContains('bad data type', $errors);
	}

	#[Test]
	public function structuredOptionNameIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'option_names' => [$this->structuredValue()],
			'option_types' => ['text'],
		]));

		$this->assertContains('bad data type', $errors);
	}

	#[Test]
	public function badOptionIdentifierIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'option_names' => ['BadName'],
			'option_types' => ['text'],
		]));

		$this->assertContains('bad identifier', $errors);
	}

	#[Test]
	public function scalarTableNamesFieldIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost(['table_names' => 0]));

		$this->assertContains('bad data type', $errors);
	}

	#[Test]
	public function structuredTableNameIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost(['table_names' => [$this->structuredValue()]]));

		$this->assertContains('bad data type', $errors);
	}

	#[Test]
	public function scalarColumnNamesFieldIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost(['column_names' => 0]));

		$this->assertContains('bad data type', $errors);
	}

	#[Test]
	public function scalarColumnListOfATableIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'table_names'  => ['demo_table'],
			'column_names' => [0 => 'id'],
		]));

		$this->assertContains('bad data type', $errors);
	}

	#[Test]
	public function structuredColumnNameIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'table_names'  => ['demo_table'],
			'column_names' => [0 => [$this->structuredValue()]],
			'column_types' => [0 => ['int']],
			'column_sizes' => [0 => [10]],
		]));

		$this->assertContains('bad data type', $errors);
	}

	#[Test]
	public function badColumnIdentifierIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'table_names'  => ['demo_table'],
			'column_names' => [0 => ['Bad-Name']],
			'column_types' => [0 => ['int']],
			'column_sizes' => [0 => [10]],
		]));

		$this->assertContains('bad identifier', $errors);
	}

	#[Test]
	public function nonScalarColumnFlagsAreRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'table_names'  => ['demo_table'],
			'column_names' => [0 => ['id']],
			'column_types' => [0 => ['int']],
			'column_sizes' => [0 => [10]],
			'column_null'  => [0 => [0 => ['on']]],
		]));

		$this->assertContains('bad data type', $errors);
	}

	#[Test]
	public function nonScalarColumnDefaultIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'table_names'     => ['demo_table'],
			'column_names'    => [0 => ['title']],
			'column_types'    => [0 => ['varchar']],
			'column_sizes'    => [0 => [255]],
			'column_defaults' => [0 => [0 => ['draft']]],
		]));

		$this->assertContains('bad data type', $errors);
	}

	#[Test]
	public function scalarTaskListIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost(['task_slugs' => 0]));

		$this->assertContains('bad data type', $errors);
	}

	#[Test]
	public function structuredTaskNameIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost([
			'task_slugs'        => [$this->structuredValue()],
			'task_regularities' => [0 => 0],
		]));

		$this->assertContains('bad data type', $errors);
	}

	#[Test]
	public function emptyHookEntryDoesNotHideAMethodConflict(): void
	{
		// An unnoticed empty entry in the hook list must not stop the collision
		// check between the generated hook and the legacy task methods
		$errors = $this->runValidator($this->basePost([
			'hooks'                    => ['', 'integrate_load_theme'],
			'legacy_task_methods'      => ['loadTheme'],
			'legacy_task_regularities' => [0 => 0],
		]));

		$this->assertContains('conflicting method', $errors);
	}

	#[Test]
	public function areaThreeFormIsAccepted(): void
	{
		// The reserved names of the third settings area must not fire on a
		// clean form, although they include the sectionExample1 method
		$errors = $this->runValidator($this->basePost([
			'settings_area' => 3,
			'hooks'         => ['integrate_admin_areas'],
		]));

		$this->assertSame([], $errors);
	}

	#[Test]
	public function emptyFormRowsAreIgnored(): void
	{
		// The "add row" buttons of the form produce empty entries, and an
		// untouched row must not turn into an error or a database object
		$rows = [
			'option' => ['option_names' => [''], 'option_types' => ['text']],
			'table'  => ['table_names' => ['']],
			'task'   => ['task_slugs' => [''], 'task_regularities' => [0 => '']],
		];

		foreach ($rows as $kind => $fields) {
			$errors = $this->runValidator($this->basePost($fields));

			$this->assertSame([], $errors, $kind);
		}
	}

	#[Test]
	public function nonStringVersionIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost(['version' => []]));

		$this->assertContains('bad version', $errors);
		$this->assertContains('bad data type', $errors);
	}

	#[Test]
	public function emptyVersionIsAccepted(): void
	{
		$errors = $this->runValidator($this->basePost(['version' => '']));

		$this->assertSame([], $errors);
	}

	#[Test]
	public function versionWithControlCharactersIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost(['version' => "1\x01.0"]));

		$this->assertContains('bad version', $errors);
	}

	#[Test]
	public function versionWithTraversalSequenceIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost(['version' => '1.0..2']));

		$this->assertContains('bad version', $errors);
	}

	#[Test]
	public function structuredTitleTranslationIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost(['title_english' => ['Demo']]));

		$this->assertContains('bad data type', $errors);
	}

	#[Test]
	public function scalarNestedTranslationsFieldIsRejected(): void
	{
		$errors = $this->runValidator($this->basePost(['option_translations' => 0]));

		$this->assertContains('bad data type', $errors);
	}

	#[Test]
	public function nestedTranslationsMustBeArraysOfScalars(): void
	{
		$perLanguage = $this->runValidator($this->basePost([
			'option_translations' => ['english' => 'Demo'],
		]));

		$this->assertContains('bad data type', $perLanguage);

		$nested = $this->runValidator($this->basePost([
			'option_translations' => ['english' => [['Demo']]],
		]));

		$this->assertContains('bad data type', $nested);
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

	/**
	 * A structured value that reaches a text field: it stays non-scalar for the
	 * schema checks, while the stringification of the length pre-checks is
	 * well-defined and does not raise a PHP warning.
	 */
	private function structuredValue(): object
	{
		return new class
		{
			public function __toString(): string
			{
				return 'demo';
			}
		};
	}

	private function runValidator(array $post): array
	{
		global $context, $txt;

		$context = [
			'smm_languages' => [['filename' => 'english']],
		];

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
			'smm_error_conflicting_method'        => 'conflicting method',
		];

		$_POST    = $post;
		$_REQUEST = $post;

		(new Validator())->validate();

		return $context['post_errors'] ?? [];
	}

	protected function tearDown(): void
	{
		$_POST    = [];
		$_REQUEST = [];
	}
}
