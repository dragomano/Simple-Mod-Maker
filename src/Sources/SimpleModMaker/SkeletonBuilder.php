<?php declare(strict_types=1);

/**
 * @package Simple Mod Maker
 * @link https://github.com/dragomano/Simple-Mod-Maker
 * @author Bugo <bugo@dragomano.ru>
 * @copyright 2022-2025 Bugo
 * @license https://opensource.org/licenses/BSD-3-Clause BSD
 *
 * @version 0.9
 */

namespace Bugo\SimpleModMaker;

if (! defined('SMF'))
	die('No direct access...');

namespace Bugo\SimpleModMaker;

class SkeletonBuilder
{
	public function build(): array
	{
		global $context, $modSettings;

		$postData = (new Validator())->validate();

		$skeleton = $this->buildBasicStructure($postData, $modSettings);
		$skeleton['options'] = $this->processOptions($postData, $skeleton);
		$skeleton['tables']  = $this->processTables($postData);

		$tasks = $this->processTasks($postData);
		$skeleton['scheduled_tasks']  = $tasks['scheduled_tasks'];
		$skeleton['background_tasks'] = $tasks['background_tasks'];
		$skeleton['legacy_tasks']     = $tasks['legacy_tasks'];
		$skeleton['make_dir']         = $skeleton['make_dir'] || $tasks['make_dir'];

		$translations = $this->processTranslations($postData, $skeleton);
		$skeleton['title']       = $translations['title'];
		$skeleton['description'] = $translations['description'];

		$skeleton['hooks'] = $this->determineHooks($skeleton);

		$context['smm_skeleton'] = $skeleton;

		return $skeleton;
	}

	private function buildBasicStructure(array $postData, array $modSettings): array
	{
		global $txt;

		$license = $postData['license'] ?? 'mit';

		return [
			'name'               => $postData['name'] ?? SMM_MODNAME_DEFAULT,
			'filename'           => $postData['filename'] ?? '',
			'hooks'              => $postData['hooks'] ?? [],
			'author'             => $modSettings['smm_mod_author'] ?? 'Unknown',
			'email'              => $modSettings['smm_mod_email'] ?? 'no-reply@simplemachines.org',
			'readmes'            => smf_json_decode($modSettings['smm_readme'] ?? '', true),
			'version'            => $postData['version'] ?? '0.1',
			'site'               => $postData['site'] ?? '',
			'settings_area'      => (int) ($postData['settings_area'] ?? 0),
			'license'            => $license,
			'license_data'       => License::getAll($txt)[$license],
			'make_dir'           => $postData['make_dir'] ?? false,
			'use_lang_dir'       => $postData['use_lang_dir'] ?? false,
			'make_template'      => $postData['make_template'] ?? false,
			'make_script'        => $postData['make_script'] ?? false,
			'make_css'           => $postData['make_css'] ?? false,
			'make_readme'        => $postData['make_readme'] ?? false,
			'add_copyrights'     => $postData['add_copyrights'] ?? false,
			'min_php_version'    => $postData['min_php_version'] ?? '',
			'smf_target_version' => $postData['smf_target_version'] ?? '2.1',
			'callbacks'          => [],
			'options'            => [],
			'tables'             => [],
			'scheduled_tasks'    => [],
			'background_tasks'   => [],
			'legacy_tasks'       => [],
			'title'              => [],
			'description'        => [],
		];
	}

	private function processOptions(array $postData, array &$skeleton): array
	{
		$options = [];

		if (empty($postData['option_names'])) {
			return $options;
		}

		foreach ($postData['option_names'] as $id => $option) {
			if (empty($option)) {
				continue;
			}

			$default = $postData['option_types'][$id] === 'check'
				? isset($postData['option_defaults'][$id])
				: ($postData['option_defaults'][$id] ?? '');

			$options[$id] = [
				'name'         => $option,
				'type'         => $postData['option_types'][$id],
				'default'      => $default,
				'variants'     => $postData['option_variants'][$id] ?? '',
				'translations' => [],
			];
		}

		if (! empty($options) && empty($skeleton['settings_area'])) {
			$skeleton['settings_area'] = 1;
		}

		return $options;
	}

	private function processTables(array $postData): array
	{
		$tables = [];

		if (empty($postData['table_names'])) {
			return $tables;
		}

		foreach ($postData['table_names'] as $id => $table) {
			if (empty($table)) {
				continue;
			}

			$tables[$id] = [
				'name'    => $table,
				'columns' => $this->processTableColumns($postData, $id),
			];
		}

		return $tables;
	}

	private function processTableColumns(array $postData, int $tableId): array
	{
		$columns = [];

		if (empty($postData['column_names'][$tableId])) {
			return $columns;
		}

		foreach ($postData['column_names'][$tableId] as $columnId => $columnName) {
			$columns[$columnId] = [
				'name'    => $columnName,
				'type'    => $postData['column_types'][$tableId][$columnId],
				'null'    => $postData['column_null'][$tableId][$columnId] ?? false,
				'size'    => $postData['column_sizes'][$tableId][$columnId] ?? 0,
				'auto'    => $postData['column_auto'][$tableId][$columnId] ?? false,
				'default' => $postData['column_defaults'][$tableId][$columnId] ?? '',
			];
		}

		return $columns;
	}

	private function processTasks(array $postData): array
	{
		$scheduledTasks  = $this->processScheduledTasks($postData);
		$backgroundTasks = $this->processBackgroundTasks($postData);
		$legacyTasks     = $this->processLegacyTasks($postData);

		$makeDir = ! (empty($scheduledTasks) && empty($backgroundTasks));

		return [
			'scheduled_tasks'  => $scheduledTasks,
			'background_tasks' => $backgroundTasks,
			'legacy_tasks'     => $legacyTasks,
			'make_dir'         => $makeDir,
		];
	}

	private function processScheduledTasks(array $postData): array
	{
		$tasks = [];

		if (empty($postData['task_slugs'])) {
			return $tasks;
		}

		foreach ($postData['task_slugs'] as $id => $taskSlug) {
			if (empty($taskSlug)) {
				continue;
			}

			$tasks[$id] = [
				'slug'         => $taskSlug,
				'names'        => [],
				'descriptions' => [],
				'regularity'   => $postData['task_regularities'][$id] ?? '',
			];
		}

		return $tasks;
	}

	private function processBackgroundTasks(array $postData): array
	{
		$tasks = [];

		if (empty($postData['background_task_classnames'])) {
			return $tasks;
		}

		foreach ($postData['background_task_classnames'] as $id => $classname) {
			if (empty($classname)) {
				continue;
			}

			$tasks[$id] = [
				'classname'  => $classname,
				'regularity' => $postData['background_task_regularities'][$id] ?? '',
			];
		}

		return $tasks;
	}

	private function processLegacyTasks(array $postData): array
	{
		$tasks = [];

		if (empty($postData['legacy_task_methods'])) {
			return $tasks;
		}

		foreach ($postData['legacy_task_methods'] as $id => $method) {
			if (empty($method)) {
				continue;
			}

			$tasks[$id] = [
				'method'     => $method,
				'regularity' => $postData['legacy_task_regularities'][$id] ?? '',
			];
		}

		return $tasks;
	}

	private function processTranslations(array $postData, array &$skeleton): array
	{
		global $context;

		$title = [];
		$description = [];

		foreach ($context['smm_languages'] as $lang) {
			$langFile = $lang['filename'];

			$title[$langFile]       = $postData['title_' . $langFile] ?? '';
			$description[$langFile] = $postData['description_' . $langFile] ?? '';

			$this->processOptionTranslations($postData, $skeleton, $langFile);
			$this->processTaskTranslations($postData, $skeleton, $langFile);
		}

		return [
			'title'       => array_filter($title),
			'description' => array_filter($description),
		];
	}

	private function processOptionTranslations(array $postData, array &$skeleton, string $langFile): void
	{
		if (empty($postData['option_translations'][$langFile]) || empty($skeleton['options'])) {
			return;
		}

		foreach ($postData['option_translations'][$langFile] as $id => $translation) {
			if (! empty($translation)) {
				$skeleton['options'][$id]['translations'][$langFile] = $translation;
			}
		}
	}

	private function processTaskTranslations(array $postData, array &$skeleton, string $langFile): void
	{
		if (! empty($postData['task_names'][$langFile]) && ! empty($skeleton['scheduled_tasks'])) {
			foreach ($postData['task_names'][$langFile] as $id => $translation) {
				if (! empty($translation)) {
					$skeleton['scheduled_tasks'][$id]['names'][$langFile] = $translation;
				}
			}
		}

		if (! empty($postData['task_descriptions'][$langFile]) && ! empty($skeleton['scheduled_tasks'])) {
			foreach ($postData['task_descriptions'][$langFile] as $id => $translation) {
				if (! empty($translation)) {
					$skeleton['scheduled_tasks'][$id]['descriptions'][$langFile] = $translation;
				}
			}
		}
	}

	private function determineHooks(array $skeleton): array
	{
		$hooks = $skeleton['hooks'];

		// Legacy tasks hooks
		if (! empty($skeleton['legacy_tasks'])) {
			foreach ($skeleton['legacy_tasks'] as $task) {
				$hooks[] = empty($task['regularity'])
					? Hook::DAILY_MAINTENANCE
					: Hook::WEEKLY_MAINTENANCE;
			}
		}

		// Settings hooks
		if (! empty($skeleton['settings_area'])) {
			$hooks = array_merge($hooks, $this->getSettingsHooks($skeleton['settings_area']));
		}

		// Copyright hook
		if (! empty($skeleton['add_copyrights'])) {
			$hooks[] = Hook::CREDITS;
		}

		return array_unique($hooks);
	}

	private function getSettingsHooks(int $settingsArea): array
	{
		return match ($settingsArea) {
			1       => [Hook::GENERAL_MOD_SETTINGS],
			2       => [Hook::ADMIN_AREAS, Hook::ADMIN_SEARCH, Hook::MODIFY_MODIFICATIONS],
			default => [Hook::ADMIN_AREAS],
		};
	}
}
