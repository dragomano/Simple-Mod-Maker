<?php

declare(strict_types=1);

/**
 * Handler.php
 *
 * @package Simple Mod Maker
 * @link https://github.com/dragomano/Simple-Mod-Maker
 * @author Bugo <bugo@dragomano.ru>
 * @copyright 2022-2024 Bugo
 * @license https://opensource.org/licenses/BSD-3-Clause BSD
 *
 * @version 0.7.2
 */

namespace Bugo\SimpleModMaker;

use Exception;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;

if (! defined('SMF'))
	die('No direct access...');

final class Handler
{
	/**
	 * @throws Exception
	 */
	public function generator(): void
	{
		global $context, $txt, $scripturl;

		loadJavaScriptFile('https://cdn.jsdelivr.net/npm/tom-select@2/dist/js/tom-select.complete.min.js', [
			'external' => true
		]);
		loadCSSFile('https://cdn.jsdelivr.net/npm/tom-select@2/dist/css/tom-select.min.css', [
			'external' => true
		]);
		loadCSSFile('simple_mod_maker.css');

		$context['page_title']      = SMM_NAME . ' - ' . $txt['smm_generator'];
		$context['page_area_title'] = $txt['smm_generator'];
		$context['canonical_url']   = $scripturl . '?action=admin;area=smm;sa=generator';

		$context[$context['admin_menu_name']]['tab_data'] = [
			'title'       => SMM_NAME,
			'description' => $txt['smm_add_desc']
		];

		$context['smm_column_types'] = SMM_COLUMN_TYPES;

		$this->prepareSkeleton();
		$this->prepareFormFields();
		$this->setData();

		$context['sub_template'] = 'modification_post';
	}

	private function prepareSkeleton(): void
	{
		global $context, $modSettings;

		$postData = (new Validator())->validate();

		$context['smm_skeleton'] = [
			'name'              => $postData['name'] ?? SMM_MODNAME_DEFAULT,
			'filename'          => $postData['filename'] ?? '',
			'hooks'             => $postData['hooks'] ?? [],
			'author'            => $modSettings['smm_mod_author'] ?? 'Unknown',
			'email'             => $modSettings['smm_mod_email'] ?? 'no-reply@simplemachines.org',
			'readmes'           => smf_json_decode($modSettings['smm_readme'] ?? '', true),
			'version'           => $postData['version'] ?? '0.1',
			'site'              => $postData['site'] ?? '',
			'settings_area'     => (int) ($postData['settings_area'] ?? 0),
			'options'           => $context['smm_skeleton']['options'] ?? [],
			'tables'            => $context['smm_skeleton']['tables'] ?? [],
			'scheduled_tasks'   => $context['smm_skeleton']['scheduled_tasks'] ?? [],
			'background_tasks'  => $context['smm_skeleton']['background_tasks'] ?? [],
			'legacy_tasks'      => $context['smm_skeleton']['legacy_tasks'] ?? [],
			'license'           => $postData['license'] ?? 'mit',
			'make_dir'          => $postData['make_dir'] ?? false,
			'use_strict_typing' => $postData['use_strict_typing'] ?? false,
			'use_final_class'   => $postData['use_final_class'] ?? false,
			'use_lang_dir'      => $postData['use_lang_dir'] ?? false,
			'make_template'     => $postData['make_template'] ?? false,
			'make_script'       => $postData['make_script'] ?? false,
			'make_css'          => $postData['make_css'] ?? false,
			'make_readme'       => $postData['make_readme'] ?? false,
			'add_copyrights'    => $postData['add_copyrights'] ?? false,
			'min_php_version'   => $postData['min_php_version'] ?? '',
			'callbacks'         => $context['smm_skeleton']['callbacks'] ?? [],
		];

		$context['smm_skeleton']['license_data'] = $this->getAvailableLicenses()[$context['smm_skeleton']['license']];

		if (! empty($postData['option_names'])) {
			foreach ($postData['option_names'] as $id => $option) {
				if (empty($option))
					continue;

				$context['smm_skeleton']['options'][$id] = [
					'name'         => $option,
					'type'         => $postData['option_types'][$id],
					'default'      => $postData['option_types'][$id] === 'check' ? isset($postData['option_defaults'][$id]) : ($postData['option_defaults'][$id] ?? ''),
					'variants'     => $postData['option_variants'][$id] ?? '',
					'translations' => []
				];
			}

			if (empty($context['smm_skeleton']['settings_area'])) {
				$context['smm_skeleton']['settings_area'] = 1;
			}
		}

		if (! empty($postData['table_names'])) {
			foreach ($postData['table_names'] as $id => $table) {
				if (empty($table))
					continue;

				$context['smm_skeleton']['tables'][$id] = [
					'name'    => $table,
					'columns' => []
				];

				if (! empty($postData['column_names'])) {
					foreach ($postData['column_names'] as $table_id => $columns) {
						foreach ($columns as $column_id => $column) {
							$context['smm_skeleton']['tables'][$table_id]['columns'][$column_id] = [
								'name'    => $postData['column_names'][$id][$column_id],
								'type'    => $postData['column_types'][$id][$column_id],
								'null'    => $postData['column_null'][$id][$column_id] ?? false,
								'size'    => $postData['column_sizes'][$id][$column_id] ?? 0,
								'auto'    => $postData['column_auto'][$id][$column_id] ?? false,
								'default' => $postData['column_defaults'][$id][$column_id] ?? '',
							];
						}
					}
				}
			}
		}

		if (! empty($postData['task_slugs'])) {
			foreach ($postData['task_slugs'] as $id => $task_slug) {
				if (empty($task_slug))
					continue;

				$context['smm_skeleton']['scheduled_tasks'][$id] = [
					'slug'         => $task_slug,
					'names'        => [],
					'descriptions' => [],
					'regularity'   => $postData['task_regularities'][$id] ?? '',
				];
			}

			$context['smm_skeleton']['make_dir'] = true;
		}

		if (! empty($postData['background_task_classnames'])) {
			foreach ($postData['background_task_classnames'] as $id => $classname) {
				if (empty($classname))
					continue;

				$context['smm_skeleton']['background_tasks'][$id] = [
					'classname'  => $classname,
					'regularity' => $postData['background_task_regularities'][$id] ?? '',
				];
			}

			$context['smm_skeleton']['make_dir'] = true;
		}

		if (! empty($postData['legacy_task_methods'])) {
			foreach ($postData['legacy_task_methods'] as $id => $method) {
				if (empty($method))
					continue;

				$context['smm_skeleton']['legacy_tasks'][$id] = [
					'method'     => $method,
					'regularity' => $postData['legacy_task_regularities'][$id] ?? '',
				];

				$context['smm_skeleton']['hooks'][] = empty($context['smm_skeleton']['legacy_tasks'][$id]['regularity']) ? 'integrate_daily_maintenance' : 'integrate_weekly_maintenance';
			}
		}

		foreach ($context['smm_languages'] as $lang) {
			$context['smm_skeleton']['title'][$lang['filename']] = $postData['title_' . $lang['filename']] ?? '';
			$context['smm_skeleton']['description'][$lang['filename']] = $postData['description_' . $lang['filename']] ?? '';

			if (! empty($postData['option_translations'][$lang['filename']])) {
				foreach ($postData['option_translations'][$lang['filename']] as $id => $translation) {
					if (! empty($translation))
						$context['smm_skeleton']['options'][$id]['translations'][$lang['filename']] = $translation;
				}
			}

			if (! empty($postData['task_names'][$lang['filename']])) {
				foreach ($postData['task_names'][$lang['filename']] as $id => $translation) {
					if (! empty($translation))
						$context['smm_skeleton']['scheduled_tasks'][$id]['names'][$lang['filename']] = $translation;
				}
			}

			if (! empty($postData['task_descriptions'][$lang['filename']])) {
				foreach ($postData['task_descriptions'][$lang['filename']] as $id => $translation) {
					if (! empty($translation))
						$context['smm_skeleton']['scheduled_tasks'][$id]['descriptions'][$lang['filename']] = $translation;
				}
			}
		}

		$context['smm_skeleton']['title']       = array_filter($context['smm_skeleton']['title']);
		$context['smm_skeleton']['description'] = array_filter($context['smm_skeleton']['description']);

		if (! empty($context['smm_skeleton']['settings_area'])) {
			switch ($context['smm_skeleton']['settings_area']) {
				case 1:
					$context['smm_skeleton']['hooks'][] = 'integrate_general_mod_settings';
					break;

				case 2:
					$context['smm_skeleton']['hooks'][] = 'integrate_admin_areas';
					$context['smm_skeleton']['hooks'][] = 'integrate_admin_search';
					$context['smm_skeleton']['hooks'][] = 'integrate_modify_modifications';
					break;

				default:
					$context['smm_skeleton']['hooks'][] = 'integrate_admin_areas';
			}
		}

		if (! empty($context['smm_skeleton']['add_copyrights']))
			$context['smm_skeleton']['hooks'][] = 'integrate_credits';

		$context['smm_skeleton']['hooks'] = array_unique($context['smm_skeleton']['hooks']);
	}

	private function prepareFormFields(): void
	{
		global $context, $txt;

		checkSubmitOnce('register');

		$this->prepareHookList();
		$this->searchHooks();

		$context['posting_fields']['name']['label']['text'] = $txt['smm_name'];
		$context['posting_fields']['name']['input'] = [
			'type' => 'text',
			'tab' => 'basic',
			'attributes' => [
				'maxlength' => 255,
				'value'     => $context['smm_skeleton']['name'],
				'required'  => true,
				'x-model'   => 'className',
			],
		];

		$context['posting_fields']['filename']['label']['text'] = $txt['smm_filename'];
		$context['posting_fields']['filename']['input'] = [
			'type' => 'text',
			'tab' => 'basic',
			'after' => $txt['smm_filename_subtext'],
			'attributes' => [
				'maxlength' => 255,
				'required'  => true,
				'pattern'   => SMM_FILENAME_PATTERN,
				':value'    => "'Class-' + className.replace(/ /g, '')",
			],
		];

		$context['posting_fields']['hooks']['label']['text'] = $txt['smm_hooks'];
		$context['posting_fields']['hooks']['input'] = [
			'type'  => 'select',
			'tab' => 'basic',
			'after' => $txt['smm_hooks_subtext'],
			'attributes' => [
				'id'       => 'hooks',
				'name'     => 'hooks[]',
				'multiple' => true
			],
			'options' => [],
		];

		$context['posting_fields']['version']['label']['text'] = $txt['smm_mod_version'];
		$context['posting_fields']['version']['input'] = [
			'type' => 'text',
			'tab' => 'basic',
			'attributes' => [
				'maxlength' => 255,
				'value'     => $context['smm_skeleton']['version'],
				'required'  => true,
			],
		];

		$context['posting_fields']['site']['label']['text'] = $txt['website'];
		$context['posting_fields']['site']['input'] = [
			'type' => 'url',
			'tab' => 'basic',
			'after' => $txt['smm_site_subtext'],
			'attributes' => [
				'maxlength'   => 255,
				'value'       => $context['smm_skeleton']['site'],
				'style'       => 'width: 100%',
				'placeholder' => 'https://github.com/dragomano/Simple-Mod-Maker',
			],
		];

		$context['posting_fields']['settings_area']['label']['text'] = $txt['smm_settings_area'];
		$context['posting_fields']['settings_area']['input'] = [
			'type' => 'select',
			'tab'  => 'settings',
			'attributes' => [
				'@change' => 'smm.changeSettingPlacement($event.target.value)',
			]
		];

		foreach ($txt['smm_settings_area_set'] as $key => $value) {
			$context['posting_fields']['settings_area']['input']['options'][$value] = [
				'value'    => $key,
				'selected' => $key === $context['smm_skeleton']['settings_area']
			];
		}

		$context['posting_fields']['title']['label']['html'] = '<label>' . $txt['smm_mod_title_and_desc'] . '</label>';
		$context['posting_fields']['title']['input']['tab']  = 'settings';
		$context['posting_fields']['title']['input']['html'] = '<div>';

		$context['posting_fields']['title']['input']['html'] .= '
			<nav' . ($context['right_to_left'] ? '' : ' class="floatleft"') . '>';

		foreach ($context['smm_languages'] as $lang) {
			$context['posting_fields']['title']['input']['html'] .= /** @lang text */
				'<a class="button floatnone" :class="{ \'active\': tab === \'' . $lang['filename'] . '\' }" @click.prevent="tab = \'' . $lang['filename'] . '\'">' . $lang['name'] . '</a>';
		}

		$context['posting_fields']['title']['input']['html'] .= '</nav>';

		foreach ($context['smm_languages'] as $lang) {
			$context['posting_fields']['title']['input']['html'] .= /** @lang text */ '
				<div x-show="tab === \'' . $lang['filename'] . '\'">
					<input
						type="text"
						name="title_' . $lang['filename'] . '"
						value="' . ($context['smm_skeleton']['title'][$lang['filename']] ?? '') . '"
						placeholder="' . $txt['smm_mod_title_default'] . '"
						x-ref="title_' . $lang['filename'] . '"
					>
					<input
						type="text"
						name="description_' . $lang['filename'] . '"
						value="' . ($context['smm_skeleton']['description'][$lang['filename']] ?? '') . '"
						placeholder="' . $txt['smm_mod_desc_default'] . '"
					>
				</div>';
		}

		$context['posting_fields']['title']['input']['html'] .= '</div>';

		$context['posting_fields']['license']['label']['text'] = $txt['smm_license'];
		$context['posting_fields']['license']['input'] = [
			'type' => 'radio_select',
			'tab' => 'basic',
		];

		foreach ($this->getAvailableLicenses() as $value => $license) {
			$context['posting_fields']['license']['input']['options'][$license['short_name']] = [
				'value'    => $value,
				'selected' => $value === $context['smm_skeleton']['license']
			];
		}

		$context['posting_fields']['make_dir']['label']['text'] = $txt['smm_make_dir'];
		$context['posting_fields']['make_dir']['input'] = [
			'type' => 'checkbox',
			'after' => $txt['smm_make_dir_subtext'],
			'attributes' => [
				'checked' => (bool) $context['smm_skeleton']['make_dir']
			],
		];

		$context['posting_fields']['use_strict_typing']['label']['text'] = $txt['smm_use_strict_typing'];
		$context['posting_fields']['use_strict_typing']['input'] = [
			'type' => 'checkbox',
			'after' => $txt['smm_use_strict_typing_subtext'],
			'attributes' => [
				'checked' => (bool) $context['smm_skeleton']['use_strict_typing']
			],
		];

		$context['posting_fields']['use_final_class']['label']['text'] = $txt['smm_use_final_class'];
		$context['posting_fields']['use_final_class']['input'] = [
			'type' => 'checkbox',
			'after' => $txt['smm_use_final_class_subtext'],
			'attributes' => [
				'checked' => (bool) $context['smm_skeleton']['use_final_class']
			],
		];

		$context['posting_fields']['use_lang_dir']['label']['text'] = $txt['smm_use_lang_dir'];
		$context['posting_fields']['use_lang_dir']['input'] = [
			'type' => 'checkbox',
			'attributes' => [
				'checked' => (bool) $context['smm_skeleton']['use_lang_dir']
			],
		];

		$context['posting_fields']['make_template']['label']['text'] = $txt['smm_make_template'];
		$context['posting_fields']['make_template']['input'] = [
			'type' => 'checkbox',
			'attributes' => [
				'checked' => (bool) $context['smm_skeleton']['make_template']
			],
		];

		$context['posting_fields']['make_script']['label']['text'] = $txt['smm_make_script'];
		$context['posting_fields']['make_script']['input'] = [
			'type' => 'checkbox',
			'attributes' => [
				'checked' => (bool) $context['smm_skeleton']['make_script']
			],
		];

		$context['posting_fields']['make_css']['label']['text'] = $txt['smm_make_css'];
		$context['posting_fields']['make_css']['input'] = [
			'type' => 'checkbox',
			'attributes' => [
				'checked' => (bool) $context['smm_skeleton']['make_css']
			],
		];

		$context['posting_fields']['make_readme']['label']['text'] = $txt['smm_make_readme'];
		$context['posting_fields']['make_readme']['input'] = [
			'type' => 'checkbox',
			'attributes' => [
				'checked' => (bool) $context['smm_skeleton']['make_readme']
			],
		];

		$context['posting_fields']['add_copyrights']['label']['text'] = $txt['smm_add_copyrights'];
		$context['posting_fields']['add_copyrights']['input'] = [
			'type' => 'checkbox',
			'after' => $txt['smm_add_copyrights_subtext'],
			'attributes' => [
				'checked' => (bool) $context['smm_skeleton']['add_copyrights']
			],
		];

		$context['posting_fields']['min_php_version']['label']['text'] = $txt['smm_min_php_version'];
		$context['posting_fields']['min_php_version']['input'] = [
			'type' => 'text',
			'tab' => 'basic',
			'attributes' => [
				'maxlength'   => 255,
				'value'       => $context['smm_skeleton']['min_php_version'],
				'placeholder' => '7.4',
			],
		];

		$this->preparePostFields();
	}

	private function preparePostFields(): void
	{
		global $context;

		foreach ($context['posting_fields'] as $item => $data) {
			if (isset($data['input']['after'])) {
				$tag = 'div';

				if (isset($data['input']['type']) && in_array($data['input']['type'], ['checkbox', 'number']))
					$tag = 'span';

				$context['posting_fields'][$item]['input']['after'] = "<$tag class=\"descbox alternative2 smalltext\">{$data['input']['after']}</$tag>";
			}

			if (isset($data['input']['type']) && $data['input']['type'] === 'checkbox') {
				$data['input']['attributes']['class'] = 'checkbox';
				$data['input']['after'] = /** @lang text */
					'<label class="label" for="' . $item . '"></label>' . ($context['posting_fields'][$item]['input']['after'] ?? '');
				$context['posting_fields'][$item] = $data;
			}

			if (empty($data['input']['tab']))
				$context['posting_fields'][$item]['input']['tab'] = 'package';
		}
	}

	private function prepareHookList(): void
	{
		global $modSettings, $context;

		$common_used_hooks = isset($modSettings['smm_hooks']) ? explode(',', $modSettings['smm_hooks']) : [];

		$hooks = array_merge($common_used_hooks, $context['smm_skeleton']['hooks']);

		sort($hooks);

		$context['smm_hook_list'] = [
			'data'  => [],
			'items' => [],
		];

		foreach ($hooks as $hook) {
			$context['smm_hook_list']['data'][] = '{text: "' . $hook . '", value: "' . $hook . '"}';

			if (in_array($hook, $context['smm_skeleton']['hooks'])) {
				$context['smm_hook_list']['items'][] = JavaScriptEscape($hook);
			}
		}
	}

	private function searchHooks(): void
	{
		global $smcFunc;

		if (! isset($_REQUEST['hooks']))
			return;

		$data = json_decode(file_get_contents('php://input'), true) ?? [];

		if (empty($data['search']))
			return;

		$search = trim($smcFunc['strtolower']($data['search']));

		if (($hooks = cache_get_data('all_smm_hooks', 30 * 24 * 60 * 60)) == null) {
			$hooks = $this->getHookList();

			cache_put_data('all_smm_hooks', $hooks, 30 * 24 * 60 * 60);
		}

		$results = array_filter($hooks, fn($item) => str_contains($item, $search));
		$results = array_map(fn($item) => ['value' => $item], $results);

		exit(json_encode($results));
	}

	private function getHookList(): array
	{
		require_once dirname(__DIR__) . '/ManageMaintenance.php';

		$hooks = array_keys(get_integration_hooks());
		$files = glob(__DIR__ . DIRECTORY_SEPARATOR . 'hooks' . DIRECTORY_SEPARATOR . 'integrate_*.php');
		$files = array_map(fn($item): string => str_replace('.php', '', basename($item)), $files);

		$list = array_merge($files, [
			'integrate_forum_stats',
			'integrate_helpadmin',
			'integrate_load_theme',
			'integrate_post_end',
			'integrate_pre_css_output',
			'integrate_theme_context',
			'integrate_user_info',
		], $hooks);

		sort($list);

		return $list;
	}

	/**
	 * @throws Exception
	 */
	private function setData(): void
	{
		global $context, $packagesdir;

		if (! empty($context['post_errors']) || empty($context['smm_skeleton']) || ! isset($_POST['save']))
			return;

		$this->rememberUsedHooks();

		$classname = strtr($context['smm_skeleton']['filename'], ['Class-' => '', '.php' => '']);

		if (empty($context['smm_skeleton']['make_dir'])) {
			$namespace = new PhpNamespace($context['smm_skeleton']['author']);
			$class = $namespace->addClass($classname);
		} else {
			$namespace = new PhpNamespace($context['smm_skeleton']['author'] . '\\' . $classname);
			$class = $namespace->addClass('Integration');
		}

		$class->addComment('Generated by ' . SMM_NAME);

		if (! empty($context['smm_skeleton']['use_final_class']))
			$class->setFinal();

		$snake_name = $this->getSnakeName($classname);

		$this->prepareUsedHooks($class, $classname, $snake_name);

		$tasks = [];

		$this->prepareScheduledTasks($tasks, $classname);

		$this->prepareBackgroundTasks($tasks, $classname);

		$this->addTaskExamples($class, $classname, $tasks);

		$content = $this->getGeneratedContent($namespace, (empty($context['smm_skeleton']['make_dir']) ? $context['smm_skeleton']['filename'] : 'Integration') . '.php');

		$plugin = new Builder([
			'skeleton'  => $context['smm_skeleton'],
			'classname' => $classname,
			'snakename' => $snake_name,
			'path'      => $packagesdir . '/' . $snake_name . '_' . $context['smm_skeleton']['version']
		]);

		$plugin->create($content)
			->createTasks($tasks)
			->createPackage();
	}

	private function prepareUsedHooks(ClassType $class, string $classname, string $snake_name): void
	{
		global $context;

		$hooks = $class->addMethod('hooks')
			->addBody("// add_integration_function('integrate_hook_name', __CLASS__ . '::methodName#', false, __FILE__);");

		if (! empty($context['smm_skeleton']['use_strict_typing']))
			$hooks->setReturnType('void');

		foreach ($context['smm_skeleton']['hooks'] as $hook) {
			$method_name = $this->getMethodName($hook);

			$hooks->addBody("add_integration_function(?, __CLASS__ . '::?#', false, __FILE__);", [$hook, $method_name]);

			$method = $class->addMethod($method_name)
				->addComment('@hook ' . $hook);

			if (file_exists($hook_file = __DIR__ . '/hooks/' . $hook . '.php')) {
				$hook_data = require_once $hook_file;

				if (! empty($hook_data['params'])) {
					foreach ($hook_data['params'] as $param => $data) {
						$parameter = isset($data[2]) ? $method->addParameter($param, $data[2]) : $method->addParameter($param);

						if (! empty($context['smm_skeleton']['use_strict_typing']))
							$parameter->setType($data[0]);

						if (! empty($data[1]))
							$parameter->setReference();
					}
				}

				if (! empty($context['smm_skeleton']['use_strict_typing']))
					$method->setReturnType(empty($hook_data['return']) ? 'void' : $hook_data['return']);

				if (! empty($hook_data['body'])) {
					foreach ($hook_data['body'] as $body) {
						$method->addBody($body);
					}
				}

				if ($hook === 'integrate_general_mod_settings' && $context['smm_skeleton']['settings_area'] === 1) {
					$this->fillConfigVars($method, $snake_name);
				}
			}
		}

		$hook_keys = array_flip($context['smm_skeleton']['hooks']);

		foreach ($context['smm_skeleton']['legacy_tasks'] as $task) {
			$taskMethod = $class->addMethod($task['method']);
			$hookName = empty($task['regularity']) ? 'integrate_daily_maintenance' : 'integrate_weekly_maintenance';
			$taskMethod->addComment("Simple task via $hookName hook");

			if (! empty($context['smm_skeleton']['use_strict_typing']))
				$taskMethod->setReturnType('void');

			$taskMethod->addBody("global \$smcFunc;" . PHP_EOL);
			$taskMethod->addBody("// Add your code here");
		}

		if ($context['smm_skeleton']['settings_area'] === 2) {
			$settings = $class->addMethod('settings');

			if (isset($hook_keys['integrate_admin_search'])) {
				$settings->addComment('@return array|void');

				$parameter = $settings->addParameter('return_config', false);

				if (! empty($context['smm_skeleton']['use_strict_typing']))
					$parameter->setType('bool');
			}

			$settings->addBody("global \$context, \$txt, \$scripturl" . (empty($context['smm_skeleton']['options']) ? '' : ", \$modSettings") . ";" . PHP_EOL);
			$settings->addBody("loadLanguage(?);" . PHP_EOL, [$classname . (empty($context['smm_skeleton']['use_lang_dir']) ? '' : '/')]);
			$settings->addBody("\$context['page_title'] = \$context['settings_title'] = \$txt['{$snake_name}_title'];");
			$settings->addBody("\$context['post_url'] = \$scripturl . '?action=admin;area=modsettings;save;sa=$snake_name';" . PHP_EOL);

			if (! empty($context['smm_skeleton']['options'])) {
				$settings->addBody("// Add default settings");
				$settings->addBody("\$addSettings = [];");

				foreach ($context['smm_skeleton']['options'] as $option) {
					if (! empty($option['default'])) {
						$settings->addBody("if (! isset(\$modSettings['{$snake_name}_{$option['name']}']))");
						$settings->addBody("\t\$addSettings['{$snake_name}_{$option['name']}'] = {$this->getDefaultValue($option)};");
					}
				}

				$settings->addBody("updateSettings(\$addSettings);" . PHP_EOL);
			}

			$this->fillConfigVars($settings, $snake_name);

			if (isset($hook_keys['integrate_admin_search'])) {
				$settings->addBody("if (\$return_config)");
				$settings->addBody("\treturn \$config_vars;" . PHP_EOL);
			}

			$settings->addBody("\$context[\$context['admin_menu_name']]['tab_data']['description'] = \$txt['{$snake_name}_description'];" . PHP_EOL);
			$settings->addBody("// Saving?");
			$settings->addBody("if (isset(\$_GET['save'])) {");
			$settings->addBody("\tcheckSession();" . PHP_EOL);
			$settings->addBody("\t\$save_vars = \$config_vars;");
			$settings->addBody("\tsaveDBSettings(\$save_vars);" . PHP_EOL);
			$settings->addBody("\tredirectexit('action=admin;area=modsettings;sa=$snake_name');");
			$settings->addBody("}" . PHP_EOL);
			$settings->addBody("prepareDBSettingContext(\$config_vars);");
		}

		if ($context['smm_skeleton']['settings_area'] === 3) {
			$settings = $class->addMethod('settings');

			$settings->addBody("global \$context, \$txt;" . PHP_EOL);

			if ($context['smm_skeleton']['make_template']) {
				$settings->addBody("loadTemplate($classname);" . PHP_EOL);
			}

			$settings->addBody("require_once dirname(__DIR__) . '/ManageSettings.php';" . PHP_EOL);
			$settings->addBody("\$context['page_title'] = \$txt['{$snake_name}_title'];");
			$settings->addBody("\$subActions = [");
			$settings->addBody("\t'section1'  => [\$this, 'sectionExample1'],");
			$settings->addBody("\t//'section2' => [\$this, 'sectionExample2'],");
			$settings->addBody("];" . PHP_EOL);
			$settings->addBody("\$context[\$context['admin_menu_name']]['tab_data'] = [");
			$settings->addBody("\t'title' => \$txt['{$snake_name}_title'],");
			$settings->addBody("\t'description' => \$txt['{$snake_name}_description'],");
			$settings->addBody("\t'tabs' => [");
			$settings->addBody("\t\t'section1'  => [],");
			$settings->addBody("\t\t//'section2'  => [],");
			$settings->addBody("\t];");
			$settings->addBody("];" . PHP_EOL);
			$settings->addBody("loadGeneralSettingParameters(\$subActions, 'section1');" . PHP_EOL);
			$settings->addBody("call_helper(\$subActions[\$context['sub_action']]);");

			$section = $class->addMethod('sectionExample1');
			$section->addComment('@return array|void');
			$parameter = $section->addParameter('return_config', false);
			if (! empty($context['smm_skeleton']['use_strict_typing']))
				$parameter->setType('bool');

			$section->addBody("global \$context, \$txt, \$scripturl;" . PHP_EOL);
			$section->addBody("require_once dirname(__DIR__) . '/ManageServer.php';" . PHP_EOL);
			$section->addBody("\$context['page_title'] .= ' - ' . \$txt['{$snake_name}_section1_title'];");
			$section->addBody("\$context['post_url'] = \$scripturl . '?action=admin;area=$snake_name;sa=section1;save';" . PHP_EOL);

			$this->fillConfigVars($section, $snake_name);

			$section->addBody("if (\$return_config)");
			$section->addBody("\treturn \$config_vars;" . PHP_EOL);
			$section->addBody("if (isset(\$_GET['save'])) {");
			$section->addBody("\tcheckSession();" . PHP_EOL);
			$section->addBody("\tsaveDBSettings(\$config_vars);" . PHP_EOL);
			$section->addBody("\tredirectexit('action=admin;area=$snake_name;sa=section1');");
			$section->addBody("}" . PHP_EOL);
			$section->addBody("prepareDBSettingContext(\$config_vars);");
		}
	}

	private function prepareScheduledTasks(array &$tasks, string $baseClassname): void
	{
		global $context;

		foreach ($context['smm_skeleton']['scheduled_tasks'] as $id => $task) {
			$classname = $this->getCamelName($task['slug']);
			$filename = $classname . '.php';

			$namespace = new PhpNamespace($context['smm_skeleton']['author'] . '\\' . $baseClassname . '\\Tasks');
			$class = $namespace->addClass($classname);
			$class->addComment('Generated by ' . SMM_NAME);

			$context['smm_skeleton']['scheduled_tasks'][$id]['callable'] = "\\\\{$context['smm_skeleton']['author']}\\\\{$baseClassname}\\\\Tasks\\\\{$classname}::execute";

			if (! empty($context['smm_skeleton']['use_final_class']))
				$class->setFinal();

			$method = $class->addMethod('execute');

			if (! empty($context['smm_skeleton']['use_strict_typing']))
				$method->setReturnType('bool');

			$method->addBody("global \$smcFunc;" . PHP_EOL);
			$method->addBody("// Add your code here" . PHP_EOL);
			$method->addBody("// Return true if everything is OK");
			$method->addBody("return true;");

			$tasks[$task['slug']]['content'] = $this->getGeneratedContent($namespace, $filename);
			$tasks[$task['slug']]['filename'] = $filename;
		}
	}

	private function prepareBackgroundTasks(array &$tasks, string $baseClassname): void
	{
		global $context;

		foreach ($context['smm_skeleton']['background_tasks'] as $id => $task) {
			$classname = $task['classname'];
			$filename = $classname . '.php';

			$namespace = new PhpNamespace($context['smm_skeleton']['author'] . '\\' . $baseClassname . '\\Tasks');
			$namespace->addUse('SMF_BackgroundTask');

			$class = $namespace->addClass($classname);
			$class->setExtends('SMF_BackgroundTask');
			$class->addComment('Generated by ' . SMM_NAME);

			$context['smm_skeleton']['background_tasks'][$id]['callable'] = "\\\\{$context['smm_skeleton']['author']}\\\\{$baseClassname}\\\\Tasks\\\\{$classname}";

			if (! empty($context['smm_skeleton']['use_final_class']))
				$class->setFinal();

			$method = $class->addMethod('execute');

			if (! empty($context['smm_skeleton']['use_strict_typing']))
				$method->setReturnType('bool');

			$method->addBody("global \$smcFunc;" . PHP_EOL);
			$method->addBody("// Add your code here" . PHP_EOL);

			if (empty($task['regularity'])) {
				$method->addBody("// Return true if everything is OK");
				$method->addBody("return true;");
			} else {
				$regularity = $task['regularity'] == 1 ? 1 : 7;
				$method->addBody("// Run task again if you need");
				$method->addBody("\$regularity = {$regularity} * 24 * 60 * 60;" . PHP_EOL);
				$method->addBody("return (bool) \$smcFunc['db_insert']('insert',");
				$method->addBody("\t'{db_prefix}background_tasks',");
				$method->addBody("\t[");
				$method->addBody("\t\t'task_file'    => 'string',");
				$method->addBody("\t\t'task_class'   => 'string',");
				$method->addBody("\t\t'task_data'    => 'string',");
				$method->addBody("\t\t'claimed_time' => 'int'");
				$method->addBody("\t],");
				$method->addBody("\t[");
				$method->addBody("\t\t'\$sourcedir/{$baseClassname}/Tasks/{$filename}',");
				$method->addBody("\t\t'\\\\' . self::class,");
				$method->addBody("\t\t'',");
				$method->addBody("\t\ttime() + \$regularity");
				$method->addBody("\t],");
				$method->addBody("\t['id_task'],");
				$method->addBody("\t1");
				$method->addBody(");" . PHP_EOL);
			}

			$tasks[$classname]['content'] = $this->getGeneratedContent($namespace, $filename);
			$tasks[$classname]['filename'] = $filename;
		}
	}

	private function addTaskExamples(ClassType $class, string $baseClassname, array $tasks): void
	{
		global $context;

		if (empty($tasks))
			return;

		foreach ($context['smm_skeleton']['background_tasks'] as $task) {
			if (! array_key_exists($task['classname'], $tasks))
				continue;

			$classname = $task['classname'];
			$filename = $tasks[$classname]['filename'];

			$method = $class->addMethod('run' . $task['classname']);

			if (! empty($context['smm_skeleton']['use_strict_typing']))
				$method->setReturnType('void');

			$method->addComment('Call this method to run a background task');
			$method->addBody("global \$smcFunc;" . PHP_EOL);
			$method->addBody("\$smcFunc['db_insert']('insert',");
			$method->addBody("\t'{db_prefix}background_tasks',");
			$method->addBody("\t[");
			$method->addBody("\t\t'task_file'  => 'string',");
			$method->addBody("\t\t'task_class' => 'string',");
			$method->addBody("\t\t'task_data'  => 'string'");
			$method->addBody("\t],");
			$method->addBody("\t[");
			$method->addBody("\t\t'\$sourcedir/{$baseClassname}/Tasks/{$filename}',");
			$method->addBody("\t\t'{$task['callable']}',");
			$method->addBody("\t\t''");
			$method->addBody("\t],");
			$method->addBody("\t['id_task']");
			$method->addBody(");" . PHP_EOL);
		}
	}

	private function getGeneratedContent(PhpNamespace $namespace, string $filename): string
	{
		global $context;

		$license = $context['smm_skeleton']['license_data'];

		$file = new PhpFile;

		if (! empty($context['smm_skeleton']['use_strict_typing']))
			$file->setStrictTypes();

		$file->addNamespace($namespace);
		$file->addComment($filename);
		$file->addComment('');
		$file->addComment("@package {$context['smm_skeleton']['name']}");
		$file->addComment("@link {$context['smm_skeleton']['site']}");
		$file->addComment("@author {$context['smm_skeleton']['author']} <{$context['smm_skeleton']['email']}>");
		$file->addComment("@copyright " . date('Y') . " {$context['smm_skeleton']['author']}");
		$file->addComment("@license {$license['link']} {$license['full_name']}");
		$file->addComment('');
		$file->addComment("@version " . $context['smm_skeleton']['version']);

		return (new Printer)->printFile($file);
	}

	private function fillConfigVars(Method $method, string $snake_name): void
	{
		global $context;

		$method->addBody("\$config_vars = [");

		if (! empty($context['smm_skeleton']['options'])) {
			foreach ($context['smm_skeleton']['options'] as $option) {
				if (in_array($option['type'], ['select-multiple', 'select'])) {
					$is_multiple = var_export($option['type'] === 'select-multiple', true);
					$option['type'] = 'select';

					$method->addBody(
						"\tarray('{$option['type']}', '{$snake_name}_{$option['name']}', \$txt['$snake_name']['{$option['name']}_set'], 'multiple' => $is_multiple),"
					);
				} else {
					$method->addBody("\tarray('{$option['type']}', '{$snake_name}_{$option['name']}'),");
				}

				if ($option['type'] === 'callback')
					$context['smm_skeleton']['callbacks'][] = $option['name'];
			}
		} else {
			$method->addBody("\t// array('check', '{$snake_name}_enable'),");
		}

		$method->addBody("];" . PHP_EOL);
	}

	private function getAvailableLicenses(): array
	{
		global $txt;

		return [
			'gpl' => [
				'short_name' => 'GPL 3.0+',
				'full_name'  => 'GPL-3.0-or-later',
				'link'       => 'https://spdx.org/licenses/GPL-3.0-or-later.html',
			],
			'mit' => [
				'short_name' => 'MIT',
				'full_name'  => 'The MIT License',
				'link'       => 'https://opensource.org/licenses/MIT',
			],
			'bsd' => [
				'short_name' => 'BSD-3-Clause',
				'full_name'  => 'The 3-Clause BSD License',
				'link'       => 'https://opensource.org/licenses/BSD-3-Clause',
			],
			'mpl' => [
				'short_name' => 'MPL-2.0',
				'full_name'  => 'Mozilla Public License 2.0',
				'link'       => 'https://opensource.org/licenses/MPL-2.0',
			],
			'own' => [
				'short_name' => $txt['smm_license_own'],
				'full_name'  => $txt['smm_license_name'],
				'link'       => $txt['smm_license_link'],
			]
		];
	}

	private function getDefaultValue(array $option): string
	{
		switch ($option['type']) {
			case 'int':
				$default = (int) $option['default'];
				break;
			case 'float':
				$default = (float) $option['default'];
				break;
			default:
				$default = $option['default'];
		}

		return var_export($default, true);
	}

	private function getSnakeName(string $value): string
	{
		return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
	}

	private function getCamelName(string $value): string
	{
		return str_replace(' ', '', ucwords(str_replace('_', ' ', $value)));
	}

	private function getMethodName(string $hook): string
	{
		return lcfirst(str_replace(' ', '', ucwords(strtr($hook, ['integrate' => '', '_' => ' ']))));
	}

	private function rememberUsedHooks(): void
	{
		global $context, $modSettings;

		if (empty($context['smm_skeleton']['hooks']))
			return;

		$used_hooks = isset($modSettings['smm_hooks']) ? explode(',', $modSettings['smm_hooks']) : [];
		updateSettings(['smm_hooks' => implode(',', array_unique(array_merge($context['smm_skeleton']['hooks'], $used_hooks)))]);
	}
}
