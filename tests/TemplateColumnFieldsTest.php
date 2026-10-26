<?php declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;

/**
 * The generator form is the only producer of the column fields, so its naming
 * rules are asserted on the template source: the markup is rendered by Alpine
 * in the browser and cannot be executed here.
 */
class TemplateColumnFieldsTest extends TestCase
{
	private const COLUMN_FIELDS = [
		'column_names',
		'column_types',
		'column_null',
		'column_sizes',
		'column_auto',
		'column_defaults',
	];

	private string $template;

	protected function setUp(): void
	{
		$path = dirname(__DIR__) . '/src/Themes/default/SimpleModMaker.template.php';

		$this->assertFileExists($path);

		// The markup lives in PHP single-quoted strings: \' and \\ are escapes.
		$this->template = strtr((string) file_get_contents($path), ["\\'" => "'", '\\\\' => '\\']);
	}

	public function testEveryColumnFieldIsPostedWithTableAndColumnIndices(): void
	{
		foreach (self::COLUMN_FIELDS as $field) {
			$this->assertStringContainsString(
				':name="`' . $field . '[${index}][${column_index}]`"',
				$this->template,
				$field . ' must be posted per table and column'
			);
		}

		$this->assertStringContainsString(':name="`table_names[${index}]`"', $this->template);
	}

	public function testColumnSizeIsBoundToTheColumnStateOnly(): void
	{
		$sizeInput = $this->inputWith('column_sizes[${index}][${column_index}]');

		$this->assertStringContainsString('x-model="column.size"', $sizeInput);
		$this->assertStringNotContainsString(':value=', $sizeInput);
	}

	private function inputWith(string $name): string
	{
		$end   = strpos($this->template, $name);
		$start = strrpos(substr($this->template, 0, (int) $end), '<input');

		return substr($this->template, (int) $start, (int) $end - (int) $start);
	}
}
