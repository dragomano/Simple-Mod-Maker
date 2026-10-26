<?php declare(strict_types=1);

/**
 * @package Simple Mod Maker
 * @link https://github.com/dragomano/Simple-Mod-Maker
 * @author Bugo <bugo@dragomano.ru>
 * @copyright 2022-2026 Bugo
 * @license https://opensource.org/licenses/BSD-3-Clause BSD
 */

namespace Bugo\SimpleModMaker;

use Bugo\SimpleModMaker\Hooks\HookFactory;
use Nette\Utils\Html;
use Throwable;

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

		$this->validateSubmission();
		$this->loadAssets();
		$this->setupPageContext($context, $txt, $scripturl);

		$skeletonBuilder = new SkeletonBuilder();
		$skeleton        = $skeletonBuilder->build();

		$this->prepareFormFields($skeleton);

		if ($this->shouldGeneratePackage($context, $skeleton)) {
			$this->generatePackage($skeleton);
		}

		$context['sub_template'] = 'modification_post';
	}

	private function validateSubmission(): void
	{
		if (! isset($_POST['save'])) {
			return;
		}

		checkSession('post');
	}

	private function registerFormSequence(): void
	{
		global $context;

		checkSubmitOnce('register');

		$_SESSION['smm_form_sequences'][$context['form_sequence_number']] = true;
		$_SESSION['smm_form_sequences'] = array_slice($_SESSION['smm_form_sequences'], -20, null, true);
	}

	private function loadAssets(): void
	{
		loadJavaScriptFile('https://cdn.jsdelivr.net/npm/tom-select@2/dist/js/tom-select.complete.min.js', [
			'external' => true,
			'defer'    => true,
		]);

		loadCSSFile('https://cdn.jsdelivr.net/npm/tom-select@2/dist/css/tom-select.min.css', [
			'external' => true
		]);

		loadJavaScriptFile('simple_mod_maker.js');

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

		$this->registerFormSequence();
		$this->prepareHookList($skeleton);
		$this->searchHooks();

		$context['posting_fields'] = array_merge(
			$this->buildBasicFields($skeleton, $txt),
			$this->buildSettingsFields($skeleton, $txt, $context),
			$this->buildPackageFields($skeleton, $txt)
		);

		// The server-rendered options keep the native select behind Tom
		// Select usable while the CDN is slow or unavailable
		$context['posting_fields']['hooks']['input']['options'] = $context['smm_hook_list']['select_options'];

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
						'value'     => Serializer::html($skeleton['name']),
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
			'namespace' => [
				'label' => ['text' => $txt['smm_namespace']],
				'input' => [
					'type'  => 'text',
					'tab'   => self::TAB['basic'],
					'after' => $txt['smm_namespace_subtext'],
					'attributes' => [
						'maxlength'   => 255,
						'value'       => Serializer::html($skeleton['namespace']),
						'required'    => true,
						'pattern'     => Validator::NAMESPACE_PATTERN,
						'placeholder' => SMM_VENDOR_DEFAULT,
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
						'value'     => Serializer::html($skeleton['version']),
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
						'value'       => Serializer::html($skeleton['site']),
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
						'value'       => Serializer::html($skeleton['min_php_version']),
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
				->setAttribute('x-on:click.prevent', "tab = '{$lang['filename']}'")
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
		$hooks           = array_values(array_unique(array_filter(array_merge($commonUsedHooks, $skeleton['hooks']))));

		sort($hooks);

		// The hook list is consumed by Tom Select inside the data script of
		// the template. It is passed as real data (serialized to JSON in one
		// place), so a hook name can never break out of the script context
		// or create markup.
		$context['smm_hook_list']['data']  = array_map(
			fn(string $hook): array => ['text' => $hook, 'value' => $hook], $hooks
		);

		$context['smm_hook_list']['items'] = array_values($skeleton['hooks']);

		// The same entries are prepared as posting field options, so the
		// native select behind Tom Select remains a working fallback
		$context['smm_hook_list']['select_options'] = array_combine(
			$hooks,
			array_map(
				fn(string $hook): array => ['value' => $hook, 'selected' => in_array($hook, $skeleton['hooks'])],
				$hooks
			)
		);
	}

	private function searchHooks(): void
	{
		// The AJAX lookup passes ;hooks in the URL, while a submitted form
		// carries the selected hooks as the hooks[] array.
		if (! isset($_REQUEST['hooks']) || ! is_string($_REQUEST['hooks'])) {
			return;
		}

		global $smcFunc;

		$data   = json_decode((string) file_get_contents('php://input'), true);
		$search = is_array($data) && is_string($data['search'] ?? null)
			? trim($smcFunc['strtolower']($data['search']))
			: '';

		if ($search === '') {
			$this->respondWithHooks([]);
		}

		$hooks = cache_get_data('all_smm_hooks', 30 * 24 * 60 * 60);

		if ($hooks === null) {
			$hooks = $this->getHookList();

			cache_put_data('all_smm_hooks', $hooks, 30 * 24 * 60 * 60);
		}

		$this->respondWithHooks($this->filterHookList($hooks, $search));
	}

	private function filterHookList(array $hooks, string $search): array
	{
		$results = array_filter($hooks, fn($item) => str_contains($item, $search));
		$results = array_map(fn($item) => ['value' => $item], $results);

		// The client iterates json.length, so a sparse result must be re-indexed.
		return array_values($results);
	}

	private function respondWithHooks(array $results): never
	{
		header('Content-Type: application/json; charset=utf-8');

		exit(json_encode($results));
	}

	private function getHookList(): array
	{
		// Load the SMF hook helpers only when they are not already available
		if (! function_exists('get_integration_hooks')) {
			require_once dirname(__DIR__) . '/ManageMaintenance.php';
		}

		$hooks = array_keys(get_integration_hooks());
		$known = HookFactory::getAvailableHooks();

		$list = array_unique(array_merge($known, [
			Hook::FORUM_STATS,
			Hook::HELP_ADMIN,
			Hook::LOAD_THEME,
			Hook::POST_END,
			Hook::PRE_CSS_OUTPUT,
			Hook::THEME_CONTEXT,
			Hook::USER_INFO,
		], $hooks));

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
		$this->rememberNamespace($skeleton);

		$classname = strtr($skeleton['filename'], ['Class-' => '', '.php' => '']);
		$snakeName = $this->toSnakeCase($classname);
		$generator = new Generator();
		$result    = $generator->generate($skeleton, $classname, $snakeName);

		$plugin = new Builder([
			'skeleton'  => $skeleton,
			'classname' => $classname,
			'snakename' => $snakeName,
			'path'      => $packagesdir . '/' . $snakeName . '_' . $skeleton['version'] . '_' . bin2hex(random_bytes(8)),
		]);

		try {
			$plugin->create($result['content'])
				->createTasks($result['tasks'])
				->createPackage();
		} catch (Throwable $e) {
			fatal_error($e->getMessage());
		}
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

	/**
	 * The vendor is remembered so that the next build does not require the same
	 * namespace to be typed again.
	 */
	private function rememberNamespace(array $skeleton): void
	{
		global $modSettings;

		// The vendor is reused on the next builds, so the latest one is kept
		if (empty($skeleton['namespace']) || ($modSettings['smm_mod_namespace'] ?? '') === $skeleton['namespace']) {
			return;
		}

		updateSettings([
			'smm_mod_namespace' => $skeleton['namespace']
		]);
	}

	private function toSnakeCase(string $value): string
	{
		return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $value));
	}
}
