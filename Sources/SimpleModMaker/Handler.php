<?php

declare(strict_types=1);

/**
 * Handler.php
 *
 * @package Simple Mod Maker
 * @link https://github.com/dragomano/Simple-Mod-Maker
 * @author Bugo <bugo@dragomano.ru>
 * @copyright 2022-2023 Bugo
 * @license https://opensource.org/licenses/BSD-3-Clause BSD
 *
 * @version 0.5.3
 */

namespace Bugo\SimpleModMaker;

use Exception;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\Printer;

if (! defined('SMF'))
	die('No direct access...');

final class Handler
{
	private const MOD_NAME_DEFAULT = 'My New Mod';

	private const MOD_FILENAME_PATTERN = '^(?:Class-)?[A-Z][a-zA-Z]+$';

	private const COLUMN_TYPES = ['tinyint', 'int', 'mediumint', 'varchar', 'text', 'mediumtext'];

	/**
	 * @throws Exception
	 */
	public function generator(): void
	{
		global $context, $txt, $scripturl;

		loadJavaScriptFile('https://cdn.jsdelivr.net/npm/tom-select/dist/js/tom-select.complete.min.js', ['external' => true]);

		loadCSSFile('https://cdn.jsdelivr.net/npm/tom-select/dist/css/tom-select.min.css', ['external' => true]);
		loadCSSFile('simple_mod_maker.css');

		$context['page_title']      = SMM_NAME . ' - ' . $txt['smm_generator'];
		$context['page_area_title'] = $txt['smm_generator'];
		$context['canonical_url']   = $scripturl . '?action=admin;area=smm;sa=generator';

		$context[$context['admin_menu_name']]['tab_data'] = [
			'title'       => SMM_NAME,
			'description' => $txt['smm_add_desc']
		];

		$context['smm_column_types'] = self::COLUMN_TYPES;

		$this->validateData();
		$this->prepareFormFields();
		$this->setData();

		$context['sub_template'] = 'modification_post';
	}

	private function validateData(): void
	{
		global $context, $modSettings;

		if (isset($_POST['save'])) {
			$post_data = $_POST;

			array_walk_recursive($post_data, fn(&$value) => $value = htmlspecialchars($value));

			$this->findErrors($post_data);
		}

		$context['smm_skeleton'] = [
			'name'              => $post_data['name'] ?? self::MOD_NAME_DEFAULT,
			'filename'          => $post_data['filename'] ?? '',
			'hooks'             => $post_data['hooks'] ?? [],
			'author'            => $modSettings['smm_mod_author'] ?? 'Unknown',
			'email'             => $modSettings['smm_mod_email'] ?? 'no-reply@simplemachines.org',
			'readmes'           => smf_json_decode($modSettings['smm_readme'] ?? '', true),
			'version'           => $post_data['version'] ?? '0.1',
			'site'              => $post_data['site'] ?? '',
			'options'           => $context['smm_skeleton']['options'] ?? [],
			'tables'            => $context['smm_skeleton']['tables'] ?? [],
			'license'           => $post_data['license'] ?? 'mit',
			'make_dir'          => $post_data['make_dir'] ?? false,
			'use_strict_typing' => $post_data['use_strict_typing'] ?? false,
			'use_final_class'   => $post_data['use_final_class'] ?? false,
			'use_lang_dir'      => $post_data['use_lang_dir'] ?? false,
			'make_template'     => $post_data['make_template'] ?? false,
			'make_script'       => $post_data['make_script'] ?? false,
			'make_css'          => $post_data['make_css'] ?? false,
			'make_readme'       => $post_data['make_readme'] ?? false,
			'add_copyrights'    => $post_data['add_copyrights'] ?? false,
			'min_php_version'   => $post_data['min_php_version'] ?? '',
			'callbacks'         => $context['smm_skeleton']['callbacks'] ?? [],
		];

		if (! empty($post_data['option_names'])) {
			foreach ($post_data['option_names'] as $id => $option) {
				if (empty($option))
					continue;

				$context['smm_skeleton']['options'][$id] = [
					'name'         => $option,
					'type'         => $post_data['option_types'][$id],
					'default'      => $post_data['option_types'][$id] === 'check' ? isset($post_data['option_defaults'][$id]) : ($post_data['option_defaults'][$id] ?? ''),
					'variants'     => $post_data['option_variants'][$id] ?? '',
					'translations' => []
				];
			}
		}

		if (! empty($post_data['table_names'])) {
			foreach ($post_data['table_names'] as $id => $table) {
				if (empty($table))
					continue;

				$context['smm_skeleton']['tables'][$id] = [
					'name'    => $table,
					'columns' => []
				];

				if (! empty($post_data['column_names'])) {
					foreach ($post_data['column_names'] as $table_id => $columns) {
						foreach ($columns as $column_id => $column) {
							$context['smm_skeleton']['tables'][$table_id]['columns'][$column_id] = [
								'name'    => $post_data['column_names'][$id][$column_id],
								'type'    => $post_data['column_types'][$id][$column_id],
								'null'    => $post_data['column_null'][$id][$column_id] ?? false,
								'size'    => $post_data['column_sizes'][$id][$column_id] ?? 0,
								'auto'    => $post_data['column_auto'][$id][$column_id] ?? false,
								'default' => $post_data['column_defaults'][$id][$column_id] ?? '',
							];
						}
					}
				}
			}
		}

		foreach ($context['languages'] as $lang) {
			$context['smm_skeleton']['title'][$lang['filename']] = $post_data['title_' . $lang['filename']] ?? '';
			$context['smm_skeleton']['description'][$lang['filename']] = $post_data['description_' . $lang['filename']] ?? '';

			if (! empty($post_data['option_translations'][$lang['filename']])) {
				foreach ($post_data['option_translations'][$lang['filename']] as $id => $translation) {
					if (! empty($translation))
						$context['smm_skeleton']['options'][$id]['translations'][$lang['filename']] = $translation;
				}
			}
		}

		$context['smm_skeleton']['title']       = array_filter($context['smm_skeleton']['title']);
		$context['smm_skeleton']['description'] = array_filter($context['smm_skeleton']['description']);

		if (! empty($context['smm_skeleton']['add_copyrights']))
			$context['smm_skeleton']['hooks'][] = 'integrate_credits';

		if (! empty($context['smm_skeleton']['options']))
			$context['smm_skeleton']['hooks'][] = 'integrate_modify_modifications';

		$context['smm_skeleton']['hooks'] = array_unique($context['smm_skeleton']['hooks']);
	}

	private function findErrors(array $data): void
	{
		global $context, $txt;

		$post_errors = [];

		if (empty($data['name']))
			$post_errors[] = 'no_name';

		if (empty($data['filename']))
			$post_errors[] = 'no_filename';

		if (! empty($data['filename']) && empty(filter_var($data['filename'], FILTER_VALIDATE_REGEXP, ['options' => ['regexp' => '/' . self::MOD_FILENAME_PATTERN . '/']])))
			$post_errors[] = 'no_valid_filename';

		if (! empty($data['option_names'])) {
			foreach ($data['option_names'] as $option) {
				if (strlen($option) > 30)
					$post_errors[] = 'option_name_too_long';
			}
		}

		if (! empty($data['table_names'])) {
			foreach ($data['table_names'] as $table) {
				if (strlen($table) > 64)
					$post_errors[] = 'table_name_too_long';
			}
		}

		if (! empty($data['column_names'])) {
			foreach ($data['column_names'] as $table) {
				foreach ($table as $column) {
					if (strlen($column) > 64)
						$post_errors[] = 'column_name_too_long';
				}
			}
		}

		if (! empty($post_errors)) {
			$context['post_errors'] = [];

			foreach ($post_errors as $error)
				$context['post_errors'][] = $txt['smm_error_' . $error];
		}
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
			'after' => $txt['smm_filename_subtext'],
			'attributes' => [
				'maxlength' => 255,
				'required'  => true,
				'pattern'   => self::MOD_FILENAME_PATTERN,
				':value'    => "'Class-' + className.replace(/ /g, '')",
			],
		];

		$context['posting_fields']['hooks']['label']['text'] = $txt['smm_hooks'];
		$context['posting_fields']['hooks']['input'] = [
			'type'  => 'select',
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
			'attributes' => [
				'maxlength' => 255,
				'value'     => $context['smm_skeleton']['version'],
				'required'  => true,
			],
		];

		$context['posting_fields']['site']['label']['text'] = $txt['website'];
		$context['posting_fields']['site']['input'] = [
			'type' => 'url',
			'after' => $txt['smm_site_subtext'],
			'attributes' => [
				'maxlength'   => 255,
				'value'       => $context['smm_skeleton']['site'],
				'style'       => 'width: 100%',
				'placeholder' => 'https://github.com/dragomano/Simple-Mod-Maker',
			],
		];

		$context['posting_fields']['title']['label']['html'] = '<label>' . $txt['smm_mod_title_and_desc'] . '</label>';
		$context['posting_fields']['title']['input']['tab']  = 'settings';
		$context['posting_fields']['title']['input']['html'] = '
			<div>';

		$context['posting_fields']['title']['input']['html'] .= '
			<nav' . ($context['right_to_left'] ? '' : ' class="floatleft"') . '>';

		foreach ($context['languages'] as $lang) {
			$context['posting_fields']['title']['input']['html'] .= '
				<a class="button floatnone" :class="{ \'active\': tab === \'' . $lang['filename'] . '\' }" @click.prevent="tab = \'' . $lang['filename'] . '\'; window.location.hash = \'' . $lang['filename'] . '\'">' . $lang['name'] . '</a>';
		}

		$context['posting_fields']['title']['input']['html'] .= '
			</nav>';

		foreach ($context['languages'] as $lang) {
			$context['posting_fields']['title']['input']['html'] .= '
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

		$context['posting_fields']['title']['input']['html'] .= '
			</div>';

		$context['posting_fields']['license']['label']['text'] = $txt['smm_license'];
		$context['posting_fields']['license']['input'] = [
			'type' => 'select',
			'tab'  => 'package'
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
			'tab' => 'package',
		];

		$context['posting_fields']['use_strict_typing']['label']['text'] = $txt['smm_use_strict_typing'];
		$context['posting_fields']['use_strict_typing']['input'] = [
			'type' => 'checkbox',
			'after' => $txt['smm_use_strict_typing_subtext'],
			'attributes' => [
				'checked' => (bool) $context['smm_skeleton']['use_strict_typing']
			],
			'tab' => 'package',
		];

		$context['posting_fields']['use_final_class']['label']['text'] = $txt['smm_use_final_class'];
		$context['posting_fields']['use_final_class']['input'] = [
			'type' => 'checkbox',
			'after' => $txt['smm_use_final_class_subtext'],
			'attributes' => [
				'checked' => (bool) $context['smm_skeleton']['use_final_class']
			],
			'tab' => 'package',
		];

		$context['posting_fields']['use_lang_dir']['label']['text'] = $txt['smm_use_lang_dir'];
		$context['posting_fields']['use_lang_dir']['input'] = [
			'type' => 'checkbox',
			'attributes' => [
				'checked' => (bool) $context['smm_skeleton']['use_lang_dir']
			],
			'tab' => 'package',
		];

		$context['posting_fields']['make_template']['label']['text'] = $txt['smm_make_template'];
		$context['posting_fields']['make_template']['input'] = [
			'type' => 'checkbox',
			'attributes' => [
				'checked' => (bool) $context['smm_skeleton']['make_template']
			],
			'tab' => 'package',
		];

		$context['posting_fields']['make_script']['label']['text'] = $txt['smm_make_script'];
		$context['posting_fields']['make_script']['input'] = [
			'type' => 'checkbox',
			'attributes' => [
				'checked' => (bool) $context['smm_skeleton']['make_script']
			],
			'tab' => 'package',
		];

		$context['posting_fields']['make_css']['label']['text'] = $txt['smm_make_css'];
		$context['posting_fields']['make_css']['input'] = [
			'type' => 'checkbox',
			'attributes' => [
				'checked' => (bool) $context['smm_skeleton']['make_css']
			],
			'tab' => 'package',
		];

		$context['posting_fields']['make_readme']['label']['text'] = $txt['smm_make_readme'];
		$context['posting_fields']['make_readme']['input'] = [
			'type' => 'checkbox',
			'attributes' => [
				'checked' => (bool) $context['smm_skeleton']['make_readme']
			],
			'tab' => 'package',
		];

		$context['posting_fields']['add_copyrights']['label']['text'] = $txt['smm_add_copyrights'];
		$context['posting_fields']['add_copyrights']['input'] = [
			'type' => 'checkbox',
			'after' => $txt['smm_add_copyrights_subtext'],
			'attributes' => [
				'checked' => (bool) $context['smm_skeleton']['add_copyrights']
			],
			'tab' => 'package',
		];

		$context['posting_fields']['min_php_version']['label']['text'] = $txt['smm_min_php_version'];
		$context['posting_fields']['min_php_version']['input'] = [
			'type' => 'text',
			'attributes' => [
				'maxlength'   => 255,
				'value'       => $context['smm_skeleton']['min_php_version'],
				'placeholder' => '7.4',
			],
			'tab' => 'package',
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
				$data['input']['after'] = '<label class="label" for="' . $item . '"></label>' . ($context['posting_fields'][$item]['input']['after'] ?? '');
				$context['posting_fields'][$item] = $data;
			}

			if (empty($data['input']['tab']))
				$context['posting_fields'][$item]['input']['tab'] = 'basic';
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

		$class->addComment('Generated by SimpleModMaker');

		if (! empty($context['smm_skeleton']['use_final_class']))
			$class->setFinal();

		$snake_name = $this->getSnakeName($classname);

		$this->prepareUsedHooks($context, $class, $classname, $snake_name);

		$licenses = $this->getAvailableLicenses()[$context['smm_skeleton']['license']];
		$license_name = $licenses['full_name'];
		$license_link = $licenses['link'];

		$file = new PhpFile;

		if (! empty($context['smm_skeleton']['use_strict_typing']))
			$file->setStrictTypes();

		$file->addNamespace($namespace);
		$file->addComment((empty($context['smm_skeleton']['make_dir']) ? $context['smm_skeleton']['filename'] : 'Integration'). '.php');
		$file->addComment('');
		$file->addComment("@package {$context['smm_skeleton']['name']}");
		$file->addComment("@link {$context['smm_skeleton']['site']}");
		$file->addComment("@author {$context['smm_skeleton']['author']} <{$context['smm_skeleton']['email']}>");
		$file->addComment("@copyright " . date('Y') . " {$context['smm_skeleton']['author']}");
		$file->addComment("@license $license_link $license_name");
		$file->addComment('');
		$file->addComment("@version " . $context['smm_skeleton']['version']);

		$content = (new class extends Printer {
			protected $indentation = "\t";
			protected $linesBetweenProperties = 1;
			protected $linesBetweenMethods = 1;
			protected $returnTypeColon = ': ';
		})->printFile($file);

		$plugin = new Builder([
			'skeleton'  => $context['smm_skeleton'],
			'classname' => $classname,
			'snakename' => $snake_name,
			'license'   => $licenses['full_name'],
			'path'      => $packagesdir . '/' . $snake_name . '_' . $context['smm_skeleton']['version']
		]);

		$plugin->create($content)
			->createPackage();
	}

	private function prepareUsedHooks(array &$context, ClassType $class, string $classname, string $snake_name): void
	{
		$hooks = $class->addMethod('hooks')
			->addBody("// add_integration_function('integrate_hook_name', __CLASS__ . '::methodName#', false, __FILE__);");

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

				if (! empty($context['smm_skeleton']['use_strict_typing']) && ! empty($hook_data['return']))
					$method->setReturnType($hook_data['return']);

				if (! empty($hook_data['body'])) {
					foreach ($hook_data['body'] as $body) {
						$method->addBody($body);
					}
				}
			}
		}

		$hook_keys = array_flip($context['smm_skeleton']['hooks']);

		if (isset($hook_keys['integrate_admin_search']) || isset($hook_keys['integrate_modify_modifications'])) {
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
				$settings->addBody("\$addSettings = [];");

				foreach ($context['smm_skeleton']['options'] as $option) {
					if (! empty($option['default'])) {
						$settings->addBody("if (! isset(\$modSettings['{$snake_name}_{$option['name']}']))");
						$settings->addBody("\t\$addSettings['{$snake_name}_{$option['name']}'] = {$this->getDefaultValue($option)};");
					}
				}

				$settings->addBody("updateSettings(\$addSettings);" . PHP_EOL);
			}

			$settings->addBody("\$config_vars = array(");

			if (! empty($context['smm_skeleton']['options'])) {
				foreach ($context['smm_skeleton']['options'] as $option) {
					if (in_array($option['type'], ['select-multiple', 'select'])) {
						$is_multiple = var_export($option['type'] === 'select-multiple', true);
						$option['type'] = 'select';

						$settings->addBody(
							"\tarray('{$option['type']}', '{$snake_name}_{$option['name']}', \$txt['$snake_name']['{$option['name']}_set'], 'multiple' => $is_multiple),"
						);
					} else {
						$settings->addBody("\tarray('{$option['type']}', '{$snake_name}_{$option['name']}'),");
					}

					if ($option['type'] === 'callback')
						$context['smm_skeleton']['callbacks'][] = $option['name'];
				}
			} else {
				$settings->addBody("\t// array('check', '{$snake_name}_enable'),");
			}

			$settings->addBody(");" . PHP_EOL);

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
