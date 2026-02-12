<?php declare(strict_types=1);

/**
 * @package Simple Mod Maker
 * @link https://github.com/dragomano/Simple-Mod-Maker
 * @author Bugo <bugo@dragomano.ru>
 * @copyright 2022-2026 Bugo
 * @license https://opensource.org/licenses/BSD-3-Clause BSD
 *
 * @version 1.0
 */

namespace Bugo\SimpleModMaker;

if (! defined('SMF'))
	die('No direct access...');

final class Validator
{
	public function validate(): array
	{
		$data = [];

		if (isset($_POST['save'])) {
			$data = $_POST;

			array_walk_recursive($data, fn(&$value) => $value = htmlspecialchars($value));

			$this->findErrors($data);
		}

		return $data;
	}

	private function findErrors(array $data): void
	{
		global $context, $txt;

		$errors = [];

		if (empty($data['name'])) {
			$errors[] = 'no_name';
		}

		if (empty($data['filename'])) {
			$errors[] = 'no_filename';
		}

		if (! empty($data['filename']) && empty(
			filter_var(
				$data['filename'],
				FILTER_VALIDATE_REGEXP,
				['options' => ['regexp' => '/' . SMM_FILENAME_PATTERN . '/']]
			))
		) {
			$errors[] = 'no_valid_filename';
		}

		if (! empty($data['option_names'])) {
			foreach ($data['option_names'] as $option) {
				if (strlen($option) > 30) {
					$errors[] = 'option_name_too_long';
				}
			}
		}

		if (! empty($data['table_names'])) {
			foreach ($data['table_names'] as $table) {
				if (strlen($table) > 64) {
					$errors[] = 'table_name_too_long';
				}
			}
		}

		if (! empty($data['column_names'])) {
			foreach ($data['column_names'] as $table) {
				foreach ($table as $column) {
					if (strlen($column) > 64) {
						$errors[] = 'column_name_too_long';
					}
				}
			}
		}

		if (! empty($errors)) {
			$context['post_errors'] = [];

			foreach ($errors as $error) {
				$context['post_errors'][] = $txt['smm_error_' . $error];
			}
		}
	}
}
