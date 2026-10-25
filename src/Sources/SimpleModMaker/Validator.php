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
	/**
	 * Shared with the form field: the namespace consists of valid PHP identifier
	 * segments and has nothing to do with the display name of the author.
	 */
	public const NAMESPACE_PATTERN = '^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)*$';

	private const LICENSES = ['gpl', 'mit', 'bsd', 'mpl', 'own'];

	private const TARGET_VERSIONS = ['2.1', '3.0', '2.1/3.0'];

	private const SETTINGS_AREAS = [0, 1, 2, 3];

	private const OPTION_TYPES = [
		'check', 'text', 'large_text', 'select', 'select-multiple', 'int', 'float',
		'bbc', 'boards', 'password', 'permissions', 'url', 'color', 'date',
		'datetime-local', 'email', 'time', 'callback',
	];

	private const COLUMN_TYPES = ['tinyint', 'int', 'mediumint', 'varchar', 'text', 'mediumtext'];

	private const INT_COLUMN_TYPES = ['tinyint', 'int', 'mediumint'];

	private const TEXT_COLUMN_TYPES = ['text', 'mediumtext'];

	private const SCHEDULED_REGULARITIES = [0, 1, 2];

	private const BACKGROUND_REGULARITIES = [0, 1, 2];

	private const LEGACY_REGULARITIES = [0, 1];

	private const RESERVED_CLASS_NAMES = [
		'abstract', 'and', 'array', 'as', 'bool', 'break', 'callable', 'case', 'catch',
		'class', 'clone', 'const', 'continue', 'declare', 'default', 'die', 'do', 'echo',
		'else', 'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach', 'endif',
		'endswitch', 'endwhile', 'exit', 'extends', 'false', 'final', 'finally', 'float',
		'for', 'foreach', 'fn', 'function', 'global', 'goto', 'if', 'implements',
		'include', 'include_once', 'instanceof', 'int', 'interface', 'isset', 'iterable',
		'list', 'match', 'mixed', 'namespace', 'never', 'new', 'null', 'or', 'parent',
		'print', 'private', 'protected', 'public', 'readonly', 'require', 'require_once',
		'return', 'self', 'static', 'string', 'switch', 'throw', 'trait', 'true', 'try',
		'unset', 'use', 'var', 'void', 'while', 'xor', 'yield',
	];

	private const SMF3_IMPORTS = ['ACP', 'Config', 'Db', 'IntegrationHook', 'Lang', 'Menu', 'Theme', 'User', 'Utils'];

	private const IDENTIFIER_PATTERNS = [
		'option_names'               => '/^[a-z][a-z_]+$/',
		'table_names'                => '/^[a-z][a-z_]+$/',
		'column_names'               => '/^[a-z][a-z_]+$/',
		'task_slugs'                 => '/^[a-z][a-z_]+$/',
		'background_task_classnames' => '/^[A-Z][a-zA-Z_]+$/',
		'legacy_task_methods'        => '/^[a-z][a-zA-Z]+$/',
	];

	private const HOOK_PATTERN = '/^[a-z][a-z0-9_]*$/';

	private const MIN_PHP_VERSION_PATTERN = '/^\d+(\.\d+){0,2}$/';

	private const MAX_LENGTHS = [
		'name'            => 255,
		'namespace'       => 255,
		'version'         => 255,
		'site'            => 255,
		'min_php_version' => 255,
	];

	private const MAX_COLUMN_SIZE = 65535;

	public function validate(): array
	{
		$data = [];

		if (isset($_POST['save'])) {
			// Store normalized source values only. Escaping is the responsibility
			// of each output context (PHP via var_export(), HTML and inline script
			// payloads via Serializer), never of the input layer.
			$data = $this->normalize($_POST);

			$this->findErrors($data);
		}

		return $data;
	}

	/**
	 * Recursively trims scalar input while preserving the original characters:
	 * quotes, ampersands, backslashes and Unicode are kept intact so that later
	 * serialization steps work with real source text instead of HTML entities.
	 */
	private function normalize(array $data): array
	{
		array_walk_recursive($data, function (&$value): void {
			if (is_scalar($value)) {
				$value = trim((string) $value);
			}
		});

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

		$filenamePattern = SMM_FILENAME_PATTERN;

		if (! str_starts_with($filenamePattern, '/')) {
			$filenamePattern = '/' . $filenamePattern . '/';
		}

		if (! empty($data['filename']) && ! is_string($data['filename'])) {
			$errors[] = 'invalid_data_type';
		} elseif (! empty($data['filename']) && empty(
			filter_var(
				$data['filename'],
				FILTER_VALIDATE_REGEXP,
				['options' => ['regexp' => $filenamePattern]]
			))
		) {
			$errors[] = 'no_valid_filename';
		}

		if (! empty($data['option_names'])) {
			foreach ($data['option_names'] as $option) {
				if (strlen((string) $option) > 30) {
					$errors[] = 'option_name_too_long';
				}
			}
		}

		if (! empty($data['table_names'])) {
			foreach ($data['table_names'] as $table) {
				if (strlen((string) $table) > 64) {
					$errors[] = 'table_name_too_long';
				}
			}
		}

		if (! empty($data['column_names'])) {
			foreach ($data['column_names'] as $table) {
				foreach ((array) $table as $column) {
					if (strlen((string) $column) > 64) {
						$errors[] = 'column_name_too_long';
					}
				}
			}
		}

		if (array_key_exists('version', $data) && ! $this->isSafeBuildVersion($data['version'])) {
			$errors[] = 'invalid_version';
		}

		$this->checkStructure($data, $errors);

		if (! empty($errors)) {
			$context['post_errors'] = [];

			foreach ($errors as $error) {
				$context['post_errors'][] = $txt['smm_error_' . $error] ?? $error;
			}
		}
	}

	/**
	 * Server-side schema for the whole form: scalar/array shapes, allowed
	 * enum values, identifiers, size ranges and consistency of related
	 * arrays. Erroneous data must never reach the generator and its file
	 * operations.
	 */
	private function checkStructure(array $data, array &$errors): void
	{
		$this->checkBasicFields($data, $errors);
		$this->checkNamespace($data, $errors);
		$this->checkOptions($data, $errors);
		$this->checkTables($data, $errors);
		$this->checkTasks($data, $errors);
		$this->checkNameConflicts($data, $errors);
		$this->checkTranslations($data, $errors);
	}

	private function checkBasicFields(array $data, array &$errors): void
	{
		foreach (['name', 'version', 'site', 'min_php_version'] as $field) {
			if (array_key_exists($field, $data) && ! is_scalar($data[$field])) {
				$errors[] = 'invalid_data_type';
			} elseif (isset(self::MAX_LENGTHS[$field])
				&& is_string($data[$field] ?? null)
				&& strlen($data[$field]) > self::MAX_LENGTHS[$field]
			) {
				$errors[] = 'invalid_data_type';
			}
		}

		if (isset($data['min_php_version']) && is_string($data['min_php_version'])
			&& $data['min_php_version'] !== ''
			&& preg_match(self::MIN_PHP_VERSION_PATTERN, $data['min_php_version']) !== 1
		) {
			$errors[] = 'invalid_data_type';
		}

		if (isset($data['hooks']) && $data['hooks'] !== []) {
			if (! $this->isScalarList($data['hooks'])) {
				$errors[] = 'invalid_data_type';
			} else {
				foreach ($data['hooks'] as $hook) {
					if (! is_string($hook) || preg_match(self::HOOK_PATTERN, $hook) !== 1) {
						$errors[] = 'invalid_data_type';
					}
				}
			}
		}

		if (isset($data['license']) && $data['license'] === '') {
			$errors[] = 'invalid_license';
		} elseif (isset($data['license']) && $data['license'] !== ''
			&& ! in_array((string) $data['license'], self::LICENSES, true)
		) {
			$errors[] = 'invalid_license';
		}

		if (isset($data['smf_target_version'])
			&& ! in_array((string) $data['smf_target_version'], self::TARGET_VERSIONS, true)
		) {
			$errors[] = 'invalid_smf_version';
		}

		if (isset($data['settings_area'])
			&& ! in_array((int) $data['settings_area'], self::SETTINGS_AREAS, true)
		) {
			$errors[] = 'invalid_settings_area';
		}

		foreach (['make_dir', 'use_lang_dir', 'make_template', 'make_script', 'make_css', 'make_readme', 'add_copyrights'] as $field) {
			if (isset($data[$field]) && ! is_scalar($data[$field])) {
				$errors[] = 'invalid_data_type';
			}
		}
	}

	/**
	 * The author name is metadata for the license block, while the code lives in
	 * a namespace of its own: display names like "Иван Петров" cannot be used
	 * as PHP namespace segments.
	 */
	private function checkNamespace(array $data, array &$errors): void
	{
		if (! isset($data['namespace'])) {
			return;
		}

		if (! is_scalar($data['namespace'])) {
			$errors[] = 'invalid_data_type';
			return;
		}

		$namespace = (string) $data['namespace'];

		// An empty value is not an error: the default vendor is used instead
		if ($namespace === '') {
			return;
		}

		if (strlen($namespace) > self::MAX_LENGTHS['namespace']
			|| preg_match('/' . self::NAMESPACE_PATTERN . '/', $namespace) !== 1
		) {
			$errors[] = 'invalid_namespace';
		}
	}

	private function checkOptions(array $data, array &$errors): void
	{
		if (! isset($data['option_names'])) {
			$this->checkRelatedScalarArray($data, 'option_types', $errors);
			$this->checkRelatedScalarArray($data, 'option_defaults', $errors);
			$this->checkRelatedScalarArray($data, 'option_variants', $errors);
			return;
		}

		if (! is_array($data['option_names'])) {
			$errors[] = 'invalid_data_type';
			return;
		}

		foreach ($data['option_names'] as $id => $name) {
			if (! is_scalar($name)) {
				$errors[] = 'invalid_data_type';
				continue;
			}

			if ($name === '') {
				continue;
			}

			if (! is_string($name) || preg_match(self::IDENTIFIER_PATTERNS['option_names'], $name) !== 1) {
				$errors[] = 'invalid_identifier';
			}

			// Consistency: every filled option must come with a known type.
			if (! isset($data['option_types'][$id])
				|| ! is_string($data['option_types'][$id])
				|| ! in_array($data['option_types'][$id], self::OPTION_TYPES, true)
			) {
				$errors[] = 'invalid_option_type';
			}

			if (isset($data['option_defaults'][$id]) && ! is_scalar($data['option_defaults'][$id])) {
				$errors[] = 'invalid_data_type';
			}
		}

		$this->checkRelatedScalarArray($data, 'option_types', $errors);
		$this->checkRelatedScalarArray($data, 'option_defaults', $errors);
		$this->checkRelatedScalarArray($data, 'option_variants', $errors);
	}

	private function checkTables(array $data, array &$errors): void
	{
		if (! isset($data['table_names'])) {
			$this->checkNestedColumns($data, $errors);
			return;
		}

		if (! is_array($data['table_names'])) {
			$errors[] = 'invalid_data_type';
			return;
		}

		foreach ($data['table_names'] as $tableId => $table) {
			if (! is_scalar($table)) {
				$errors[] = 'invalid_data_type';
				continue;
			}

			if ($table === '') {
				continue;
			}

			if (! is_string($table) || preg_match(self::IDENTIFIER_PATTERNS['table_names'], $table) !== 1) {
				$errors[] = 'invalid_identifier';
			}

			// A table declared without a single column would become an empty CREATE TABLE
			if ($this->getStringValues($data['column_names'][$tableId] ?? []) === []) {
				$errors[] = 'empty_columns';
			}
		}

		$this->checkNestedColumns($data, $errors);
	}

	private function checkNestedColumns(array $data, array &$errors): void
	{
		$columnFields = ['column_names', 'column_types', 'column_null', 'column_sizes', 'column_auto', 'column_defaults'];

		foreach ($columnFields as $field) {
			if (! isset($data[$field])) {
				continue;
			}

			if (! is_array($data[$field])) {
				$errors[] = 'invalid_data_type';
				return;
			}
		}

		if (empty($data['column_names']) || ! is_array($data['column_names'])) {
			return;
		}

		foreach ($data['column_names'] as $tableId => $columns) {
			if (! is_array($columns)) {
				$errors[] = 'invalid_data_type';
				continue;
			}

			$names       = [];
			$autoColumns = 0;

			foreach ($columns as $columnId => $column) {
				if (! is_scalar($column)) {
					$errors[] = 'invalid_data_type';
					continue;
				}

				if ($column === '') {
					continue;
				}

				if (! is_string($column) || preg_match(self::IDENTIFIER_PATTERNS['column_names'], $column) !== 1) {
					$errors[] = 'invalid_identifier';
				} elseif (isset($names[strtolower($column)])) {
					$errors[] = 'duplicate_columns';
				} else {
					$names[strtolower($column)] = true;
				}

				foreach (['column_null', 'column_auto'] as $flag) {
					if (isset($data[$flag][$tableId][$columnId]) && ! is_scalar($data[$flag][$tableId][$columnId])) {
						$errors[] = 'invalid_data_type';
					}
				}

				// Consistency: every filled column must come with a known type.
				if (! isset($data['column_types'][$tableId][$columnId])
					|| ! is_string($data['column_types'][$tableId][$columnId])
					|| ! in_array($data['column_types'][$tableId][$columnId], self::COLUMN_TYPES, true)
				) {
					$errors[] = 'invalid_column_type';
					continue;
				}

				$type   = $data['column_types'][$tableId][$columnId];
				$isInt  = in_array($type, self::INT_COLUMN_TYPES, true);
				$isText = in_array($type, self::TEXT_COLUMN_TYPES, true);
				$auto   = ! empty($data['column_auto'][$tableId][$columnId] ?? false);
				$null   = ! empty($data['column_null'][$tableId][$columnId] ?? false);

				// Flags that do not suit the type are dropped by the generator silently
				if (($auto && ! $isInt) || ($null && ! $isText) || ($isText && isset($data['column_sizes'][$tableId][$columnId]))) {
					$errors[] = 'invalid_column_attributes';
				}

				if ($auto && $isInt) {
					$autoColumns++;
				}

				if (isset($data['column_sizes'][$tableId][$columnId])) {
					$size = $data['column_sizes'][$tableId][$columnId];

					if (! is_scalar($size) || ! preg_match('/^\d+$/', (string) $size)
						|| (int) $size < 1 || (int) $size > self::MAX_COLUMN_SIZE
					) {
						$errors[] = 'invalid_size';
					}
				}

				if (isset($data['column_defaults'][$tableId][$columnId])) {
					$default = $data['column_defaults'][$tableId][$columnId];

					if (! is_scalar($default)) {
						$errors[] = 'invalid_data_type';
					} elseif ((string) $default !== '') {
						if ($isText || $auto) {
							$errors[] = 'invalid_column_attributes';
						} elseif ($isInt && preg_match('/^\d+$/', (string) $default) !== 1) {
							$errors[] = 'invalid_default';
						}
					}
				}
			}

			// MySQL allows a single auto-increment column per table
			if ($autoColumns > 1) {
				$errors[] = 'multiple_auto_columns';
			}
		}
	}

	private function checkTasks(array $data, array &$errors): void
	{
		// Scheduled tasks
		$this->checkTaskList($data, 'task_slugs', 'task_regularities', self::SCHEDULED_REGULARITIES, $errors);

		// Background tasks
		$this->checkTaskList(
			$data,
			'background_task_classnames',
			'background_task_regularities',
			self::BACKGROUND_REGULARITIES,
			$errors
		);

		// Legacy tasks
		$this->checkTaskList($data, 'legacy_task_methods', 'legacy_task_regularities', self::LEGACY_REGULARITIES, $errors);
	}

	private function checkTaskList(array $data, string $nameField, string $regularityField, array $allowedRegularities, array &$errors): void
	{
		if (! isset($data[$nameField])) {
			$this->checkRelatedScalarArray($data, $regularityField, $errors);
			return;
		}

		if (! is_array($data[$nameField])) {
			$errors[] = 'invalid_data_type';
			return;
		}

		foreach ($data[$nameField] as $id => $value) {
			if (! is_scalar($value)) {
				$errors[] = 'invalid_data_type';
				continue;
			}

			if ($value === '') {
				continue;
			}

			if (! is_string($value) || preg_match(self::IDENTIFIER_PATTERNS[$nameField], $value) !== 1) {
				$errors[] = 'invalid_identifier';
			}

			// Consistency: every filled task must come with an allowed regularity.
			if (! isset($data[$regularityField][$id])
				|| ! is_scalar($data[$regularityField][$id])
				|| ! in_array((int) $data[$regularityField][$id], $allowedRegularities, true)
			) {
				$errors[] = 'invalid_regularities';
			}
		}

		$this->checkRelatedScalarArray($data, $regularityField, $errors);
	}

	private function checkNameConflicts(array $data, array &$errors): void
	{
		$this->checkTaskClassnames($data, $errors);
		$this->checkMethodNames($data, $errors);
		$this->checkModClassname($data, $errors);
	}

	private function checkTaskClassnames(array $data, array &$errors): void
	{
		$classnames = $this->getTaskClassnames($data);

		foreach ($classnames as $classname) {
			if (in_array(strtolower($classname), self::RESERVED_CLASS_NAMES, true)) {
				$errors[] = 'reserved_name';
			}
		}

		if (count($classnames) !== count(array_unique(array_map(strtolower(...), $classnames)))) {
			$errors[] = 'conflicting_task_class';
		}
	}

	private function checkMethodNames(array $data, array &$errors): void
	{
		$legacyMethods   = array_map(strtolower(...), $this->getStringValues($data['legacy_task_methods'] ?? []));
		$hookMethods     = array_map(strtolower(...), $this->getHookMethodNames($data));
		$reservedMethods = array_map(strtolower(...), $this->getGeneratorMethodNames($data));

		foreach ([$legacyMethods, $hookMethods] as $methods) {
			if (count($methods) !== count(array_unique($methods))) {
				$errors[] = 'conflicting_method';
			}
		}

		foreach ($legacyMethods as $method) {
			if (in_array($method, $reservedMethods, true) || in_array($method, $hookMethods, true)) {
				$errors[] = 'conflicting_method';
			}
		}
	}

	private function checkModClassname(array $data, array &$errors): void
	{
		if (! empty($data['make_dir']) || $this->getTaskClassnames($data) !== []) {
			return;
		}

		if (! str_contains((string) ($data['smf_target_version'] ?? ''), '3.0')) {
			return;
		}

		$classname = strtr((string) ($data['filename'] ?? ''), ['Class-' => '', '.php' => '']);

		if ($classname !== '' && in_array(strtolower($classname), array_map(strtolower(...), self::SMF3_IMPORTS), true)) {
			$errors[] = 'reserved_name';
		}
	}

	private function getTaskClassnames(array $data): array
	{
		$classnames = [];

		foreach ($this->getStringValues($data['task_slugs'] ?? []) as $slug) {
			// Mirrors Generator::toCamelCase()
			$classnames[] = str_replace(' ', '', ucwords(str_replace('_', ' ', $slug)));
		}

		$classnames = array_merge(
			$classnames,
			$this->getStringValues($data['background_task_classnames'] ?? [])
		);

		return array_values($classnames);
	}

	private function getHookMethodNames(array $data): array
	{
		$hooks = array_values(array_unique(array_filter((array) ($data['hooks'] ?? []), is_string(...))));

		$methods = [];

		foreach ($hooks as $hook) {
			if ($hook === '') {
				continue;
			}

			// Mirrors Generator::getMethodName()
			$methods[] = lcfirst(str_replace(' ', '', ucwords(strtr($hook, ['integrate' => '', '_' => ' ']))));
		}

		return $methods;
	}

	private function getGeneratorMethodNames(array $data): array
	{
		$methods = ['hooks'];
		$area    = (int) ($data['settings_area'] ?? 0);

		if ($area === 2 || $area === 3) {
			$methods[] = 'settings';
		}

		if ($area === 3) {
			$methods[] = 'sectionExample1';
		}

		foreach ($this->getStringValues($data['background_task_classnames'] ?? []) as $classname) {
			$methods[] = 'run' . $classname;
		}

		return $methods;
	}

	private function getStringValues(mixed $values): array
	{
		$result = [];

		foreach ((array) $values as $value) {
			if (is_string($value) && $value !== '') {
				$result[] = $value;
			}
		}

		return $result;
	}

	private function checkTranslations(array $data, array &$errors): void
	{
		global $context;

		$languages = array_column($context['smm_languages'] ?? [], 'filename');

		// Title and description per language
		foreach ($languages as $langFile) {
			foreach (['title_' . $langFile, 'description_' . $langFile] as $field) {
				if (isset($data[$field]) && ! is_scalar($data[$field])) {
					$errors[] = 'invalid_data_type';
				}
			}
		}

		// Nested translations: option_translations, task_names, task_descriptions
		$nestedFields = ['option_translations', 'task_names', 'task_descriptions'];

		foreach ($nestedFields as $field) {
			if (! isset($data[$field])) {
				continue;
			}

			if (! is_array($data[$field])) {
				$errors[] = 'invalid_data_type';
				continue;
			}

			foreach ($data[$field] as $translations) {
				if (! is_array($translations) || ! $this->isScalarValues($translations)) {
					$errors[] = 'invalid_data_type';
				}
			}
		}
	}

	/**
	 * An optional sibling array (types, defaults, regularities, etc.) must be
	 * an array of scalars when present at all. Sparse keys are legitimate:
	 * unchecked checkboxes simply do not arrive in the POST payload.
	 */
	private function checkRelatedScalarArray(array $data, string $field, array &$errors): void
	{
		if (! isset($data[$field])) {
			return;
		}

		if (! is_array($data[$field]) || ! $this->isScalarValues($data[$field])) {
			$errors[] = 'invalid_data_type';
		}
	}

	private function isScalarList(mixed $value): bool
	{
		return is_array($value) && array_is_list($value) && $this->isScalarValues($value);
	}

	private function isScalarValues(array $value): bool
	{
		foreach ($value as $item) {
			if (! is_scalar($item)) {
				return false;
			}
		}

		return true;
	}

	private function isSafeBuildVersion(mixed $value): bool
	{
		if (! is_string($value)) {
			return false;
		}

		if ($value === '') {
			return true;
		}

		if (strpbrk($value, "/\\\x00") !== false) {
			return false;
		}

		if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1) {
			return false;
		}

		if (str_contains($value, '..')) {
			return false;
		}

		return true;
	}
}
