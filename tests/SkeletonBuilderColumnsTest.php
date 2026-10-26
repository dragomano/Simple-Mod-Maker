<?php declare(strict_types=1);

namespace Tests;

use Bugo\SimpleModMaker\SkeletonBuilder;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class SkeletonBuilderColumnsTest extends TestCase
{
	/**
	 * The template posts every column attribute under an explicit
	 * [tableIndex][columnIndex] pair, so unchecked checkboxes and the size
	 * field, which is absent for text columns, simply leave a key missing.
	 */
	public function testColumnsKeepTheirOwnAttributesWithSparseFields(): void
	{
		$tables = $this->processTables([
			'table_names'     => [0 => 'demo_table'],
			'column_names'    => [0 => [0 => 'notes', 1 => 'title', 2 => 'id']],
			'column_types'    => [0 => [0 => 'text', 1 => 'varchar', 2 => 'int']],
			'column_null'     => [0 => [0 => 'on']],
			'column_auto'     => [0 => [2 => 'on']],
			'column_sizes'    => [0 => [1 => '100', 2 => '10']],
			'column_defaults' => [0 => [1 => 'none']],
		]);

		$this->assertSame([
			[
				'name'    => 'demo_table',
				'columns' => [
					[
						'name'    => 'notes',
						'type'    => 'text',
						'null'    => true,
						'size'    => 0,
						'auto'    => false,
						'default' => '',
					],
					[
						'name'    => 'title',
						'type'    => 'varchar',
						'null'    => false,
						'size'    => 100,
						'auto'    => false,
						'default' => 'none',
					],
					[
						'name'    => 'id',
						'type'    => 'int',
						'null'    => false,
						'size'    => 10,
						'auto'    => true,
						'default' => '',
					],
				],
			],
		], $tables);
	}

	public function testStaleFieldsOfAnotherTypeAreDropped(): void
	{
		$tables = $this->processTables([
			'table_names'     => [0 => 'demo_table'],
			'column_names'    => [0 => [0 => 'title', 1 => 'body', 2 => 'id']],
			'column_types'    => [0 => [0 => 'varchar', 1 => 'text', 2 => 'int']],
			'column_auto'     => [0 => [0 => 'on', 2 => 'on']],
			'column_null'     => [0 => [1 => 'on', 2 => 'on']],
			'column_sizes'    => [0 => [0 => '255', 1 => '128', 2 => '10']],
			'column_defaults' => [0 => [1 => 'stale', 2 => 'stale']],
		]);

		$columns = $tables[0]['columns'];

		$this->assertSame(
			[
				'name'    => 'title',
				'type'    => 'varchar',
				'null'    => false,
				'size'    => 255,
				'auto'    => false,
				'default' => '',
			],
			$columns[0]
		);
		$this->assertSame(['size' => 0, 'auto' => false, 'default' => ''], [
			'size'    => $columns[1]['size'],
			'auto'    => $columns[1]['auto'],
			'default' => $columns[1]['default'],
		]);
		$this->assertSame(
			[
				'name'    => 'id',
				'type'    => 'int',
				'null'    => false,
				'size'    => 10,
				'auto'    => true,
				'default' => '',
			],
			$columns[2]
		);
	}

	/**
	 * The client re-renders the submitted tables from this array, so both
	 * levels have to stay plain JSON lists.
	 */
	public function testTablesAndColumnsAreSerializedAsLists(): void
	{
		$tables = $this->processTables([
			'table_names'  => [0 => '', 1 => 'demo_table'],
			'column_names' => [1 => [3 => 'title', 4 => 'body']],
			'column_types' => [1 => [3 => 'varchar', 4 => 'mediumtext']],
		]);

		$this->assertSame(['demo_table'], array_column($tables, 'name'));
		$this->assertTrue(array_is_list($tables));
		$this->assertTrue(array_is_list($tables[0]['columns']));
		$this->assertStringStartsWith(
			'[{"name":"demo_table","columns":[{"name":"title"',
			(string) json_encode($tables)
		);
	}

	private function processTables(array $postData): array
	{
		$builder = new SkeletonBuilder();
		$method  = new ReflectionMethod($builder, 'processTables');

		return $method->invoke($builder, $postData);
	}
}
