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

use Bugo\SimpleModMaker\Hooks\HookFactory;
use Nette\Utils\Html;

if (! defined('SMF'))
	die('No direct access...');

final class Handler
{
	private const COLUMN_TYPES = ['tinyint', 'int', 'mediumint', 'varchar', 'text', 'mediumtext'];

	private const TAB = [
		'basic'    => 'basic',
		'settings' => 'settings',
		'package'  => 'package',
	];

	public function generator(): void
	{
		global $context, $txt, $scripturl;

		$this->loadAssets();
		$this->setupPageContext($context, $txt, $scripturl);

		$skeletonBuilder = new SkeletonBuilder();
		$skeleton = $skeletonBuilder->build();

		$this->prepareFormFields($skeleton);

		if ($this->shouldGeneratePackage($context, $skeleton)) {
			$this->generatePackage($skeleton);
		}

		$context['sub_template'] = 'modification_post';
	}

	private function loadAssets(): void
	{
		loadJavaScriptFile('https://cdn.jsdelivr.net/npm/tom-select@2/dist/js/tom-select.complete.min.js', [
			'external' => true
		]);

		loadCSSFile('https://cdn.jsdelivr.net/npm/tom-select@2/dist/css/tom-select.min.css', [
			'external' => true
		]);

		loadCSSFile('simple_mod_maker.css');
	}

	private function setupPageContext(array &$context, array $txt, string $scripturl): void
	{
		$context['page_title']      = SMM_NAME . ' - ' . $txt['smm_generator'];
		$context['page_area_title'] = $txt['smm_generator'];
		$context['canonical_url']   = $scripturl . '?action=admin;area=smm;sa=generator';

		$context[$context['admin_menu_name']]['tab_data'] = [
			'title'       => SMM_NAME,
			'description' => $txt['smm_add_desc'],
		];

		$context['smm_column_types'] = self::COLUMN_TYPES;
	}

	private function prepareFormFields(array $skeleton): void
	{
		global $context, $txt;

		checkSubmitOnce('register');

		$this->prepareHookList($skeleton);
		$this->searchHooks();

		$context['posting_fields'] = array_merge(
			$this->buildBasicFields($skeleton, $txt),
			$this->buildSettingsFields($skeleton, $txt, $context),
			$this->buildPackageFields($skeleton, $txt)
		);

		$this->postProcessFields($context['posting_fields']);
	}

	private function buildBasicFields(array $skeleton, array $txt): array
	{
		$fields = [
			'name' => [
				'label' => ['text' => $txt['smm_name']],
				'input' => [
					'type' => 'text',
					'tab' => self::TAB['basic'],
					'attributes' => [
						'maxlength' => 255,
						'value'     => $skeleton['name'],
						'required'  => true,
						'x-model'   => 'className',
					],
				],
			],
			'filename' => [
				'label' => ['text' => $txt['smm_filename']],
				'input' => [
					'type' => 'text',
					'tab' => self::TAB['basic'],
					'after' => $txt['smm_filename_subtext'],
					'attributes' => [
						'maxlength' => 255,
						'required'  => true,
						'pattern'   => SMM_FILENAME_PATTERN,
						':value'    => "'Class-' + className.replace(/ /g, '')",
					],
				],
			],
			'hooks' => [
				'label' => ['text' => $txt['smm_hooks']],
				'input' => [
					'type'  => 'select',
					'tab' => self::TAB['basic'],
					'after' => $txt['smm_hooks_subtext'],
					'attributes' => [
						'id'       => 'hooks',
						'name'     => 'hooks[]',
						'multiple' => true,
					],
					'options' => [],
				],
			],
			'version' => [
				'label' => ['text' => $txt['smm_mod_version']],
				'input' => [
					'type' => 'text',
					'tab' => self::TAB['basic'],
					'attributes' => [
						'maxlength' => 255,
						'value'     => $skeleton['version'],
						'required'  => true,
					],
				],
			],
			'site' => [
				'label' => ['text' => $txt['website']],
				'input' => [
					'type' => 'url',
					'tab' => self::TAB['basic'],
					'after' => $txt['smm_site_subtext'],
					'attributes' => [
						'maxlength'   => 255,
						'value'       => $skeleton['site'],
						'style'       => 'width: 100%',
						'placeholder' => 'https://github.com/dragomano/Simple-Mod-Maker',
					],
				],
			],
			'license' => [
				'label' => ['text' => $txt['smm_license']],
				'input' => [
					'type' => 'radio_select',
					'tab'  => self::TAB['basic'],
					'options' => [],
				],
			],
			'min_php_version' => [
				'label' => ['text' => $txt['smm_min_php_version']],
				'input' => [
					'type' => 'text',
					'tab' => self::TAB['basic'],
					'attributes' => [
						'maxlength'   => 255,
						'value'       => $skeleton['min_php_version'],
						'placeholder' => '8.0',
					],
				],
			],
			'smf_target_version' => [
				'label' => ['text' => $txt['smm_smf_target_version']],
				'input' => [
					'type' => 'radio_select',
					'tab'  => self::TAB['basic'],
					'options' => [],
				],
			],
		];

		// Populate license options
		foreach (License::getAll($txt) as $value => $license) {
			$fields['license']['input']['options'][$license['short_name']] = [
				'value'    => $value,
				'selected' => $value === $skeleton['license'],
			];
		}

		// Populate SMF version options
		foreach (['2.1', '3.0', '2.1/3.0'] as $value) {
			$fields['smf_target_version']['input']['options'][$value] = [
				'value'    => $value,
				'selected' => $value === $skeleton['smf_target_version'],
			];
		}

		return $fields;
	}

	private function buildSettingsFields(array $skeleton, array $txt, array $context): array
	{
		$fields = [
			'settings_area' => [
				'label' => ['text' => $txt['smm_settings_area']],
				'input' => [
					'type' => 'select',
					'tab'  => self::TAB['settings'],
					'attributes' => [
						'@change' => 'smm.changeSettingPlacement($event.target.value)',
					],
					'options' => [],
				],
			],
		];

		foreach ($txt['smm_settings_area_set'] as $key => $value) {
			$fields['settings_area']['input']['options'][$value] = [
				'value'    => $key,
				'selected' => $key === $skeleton['settings_area'],
			];
		}

		// Build title/description fields with language tabs
		$root = Html::el('div');

		$nav = $root->create('nav');
		if (empty($context['right_to_left'])) {
			$nav->class('floatleft');
		}

		foreach ($context['smm_languages'] as $lang) {
			$nav->create('a')
				->setAttribute('class', 'button floatnone')
				->setAttribute(':class', "{ 'active': tab === '{$lang['filename']}' }")
				->setAttribute('@click.prevent', "tab = '{$lang['filename']}'")
				->setText($lang['name']);
		}

		foreach ($context['smm_languages'] as $lang) {
			$langFile = $lang['filename'];

			$div = $root->create('div')
				->setAttribute('x-show', "tab === '$langFile'");

			$div->create('input')
				->setAttribute('type', 'text')
				->setAttribute('name', "title_$langFile")
				->setAttribute('value', $skeleton['title'][$langFile] ?? '')
				->setAttribute('placeholder', $txt['smm_mod_title_default'])
				->setAttribute('x-ref', "title_$langFile");

			$div->create('input')
				->setAttribute('type', 'text')
				->setAttribute('name', "description_$langFile")
				->setAttribute('value', $skeleton['description'][$langFile] ?? '')
				->setAttribute('placeholder', $txt['smm_mod_desc_default']);
		}

		$html = $root->render();

		$fields['title'] = [
			'label' => ['html' => Html::el('label', $txt['smm_mod_title_and_desc'])],
			'input' => [
				'tab'  => self::TAB['settings'],
				'html' => $html,
			],
		];

		return $fields;
	}

	private function buildPackageFields(array $skeleton, array $txt): array
	{
		$checkboxFields = [
			'make_dir'       => ['smm_make_dir', 'smm_make_dir_subtext'],
			'use_lang_dir'   => ['smm_use_lang_dir', null],
			'make_template'  => ['smm_make_template', null],
			'make_script'    => ['smm_make_script', null],
			'make_css'       => ['smm_make_css', null],
			'make_readme'    => ['smm_make_readme', null],
			'add_copyrights' => ['smm_add_copyrights', 'smm_add_copyrights_subtext'],
		];

		$fields = [];

		foreach ($checkboxFields as $field => [$label, $subtext]) {
			$fields[$field] = [
				'label' => ['text' => $txt[$label]],
				'input' => [
					'type' => 'checkbox',
					'attributes' => [
						'checked' => (bool) $skeleton[$field],
					],
				],
			];

			if ($subtext) {
				$fields[$field]['input']['after'] = $txt[$subtext];
			}
		}

		return $fields;
	}

	private function postProcessFields(array &$fields): void
	{
		foreach ($fields as $item => &$data) {
			if (isset($data['input']['after'])) {
				$tag = in_array($data['input']['type'] ?? '', ['checkbox', 'number']) ? 'span' : 'div';
				$data['input']['after'] = "<$tag class=\"descbox alternative2 smalltext\">{$data['input']['after']}</$tag>";
			}

			if (isset($data['input']['type']) && $data['input']['type'] === 'checkbox') {
				$data['input']['attributes']['class'] = 'checkbox';
				$data['input']['after'] = '<label class="label" for="' . $item . '"></label>' . ($data['input']['after'] ?? '');
			}

			if (empty($data['input']['tab'])) {
				$data['input']['tab'] = self::TAB['package'];
			}
		}
	}

	private function prepareHookList(array $skeleton): void
	{
		global $modSettings, $context;

		$commonUsedHooks = isset($modSettings['smm_hooks']) ? explode(',', $modSettings['smm_hooks']) : [];
		$hooks = array_merge($commonUsedHooks, $skeleton['hooks']);
		sort($hooks);

		$context['smm_hook_list'] = [
			'data'  => [],
			'items' => [],
		];

		foreach ($hooks as $hook) {
			$context['smm_hook_list']['data'][] = '{text: "' . $hook . '", value: "' . $hook . '"}';

			if (in_array($hook, $skeleton['hooks'])) {
				$context['smm_hook_list']['items'][] = JavaScriptEscape($hook);
			}
		}
	}

	private function searchHooks(): void
	{
		if (! isset($_REQUEST['hooks'])) {
			return;
		}

		global $smcFunc;

		$data = json_decode(file_get_contents('php://input'), true) ?? [];

		if (empty($data['search'])) {
			return;
		}

		$search = trim($smcFunc['strtolower']($data['search']));

		$hooks = cache_get_data('all_smm_hooks', 30 * 24 * 60 * 60);

		if ($hooks === null) {
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
		$known = HookFactory::getAvailableHooks();

		$list = array_merge($known, [
			Hook::FORUM_STATS,
			Hook::HELP_ADMIN,
			Hook::LOAD_THEME,
			Hook::POST_END,
			Hook::PRE_CSS_OUTPUT,
			Hook::THEME_CONTEXT,
			Hook::USER_INFO,
		], $hooks);

		sort($list);

		return $list;
	}

	private function shouldGeneratePackage(array $context, array $skeleton): bool
	{
		return empty($context['post_errors']) && ! empty($skeleton) && isset($_POST['save']);
	}

	private function generatePackage(array $skeleton): void
	{
		global $packagesdir;

		$this->rememberUsedHooks($skeleton);

		$classname = strtr($skeleton['filename'], ['Class-' => '', '.php' => '']);
		$snakeName = $this->toSnakeCase($classname);

		$generator = new Generator();
		$result = $generator->generate($skeleton, $classname, $snakeName);

		$plugin = new Builder([
			'skeleton'  => $skeleton,
			'classname' => $classname,
			'snakename' => $snakeName,
			'path'      => $packagesdir . '/' . $snakeName . '_' . $skeleton['version']
		]);

		$plugin->create($result['content'])
			->createTasks($result['tasks'])
			->createPackage();
	}

	private function rememberUsedHooks(array $skeleton): void
	{
		global $modSettings;

		if (empty($skeleton['hooks'])) {
			return;
		}

		$usedHooks = isset($modSettings['smm_hooks']) ? explode(',', $modSettings['smm_hooks']) : [];

		updateSettings([
			'smm_hooks' => implode(',', array_unique(array_merge($skeleton['hooks'], $usedHooks)))
		]);
	}

	private function toSnakeCase(string $value): string
	{
		return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
	}
}
