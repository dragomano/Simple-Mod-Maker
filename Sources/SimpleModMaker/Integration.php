<?php

declare(strict_types=1);

/**
 * Integration.php
 *
 * @package Simple Mod Maker
 * @link https://github.com/dragomano/Simple-Mod-Maker
 * @author Bugo <bugo@dragomano.ru>
 * @copyright 2022-2023 Bugo
 * @license https://opensource.org/licenses/BSD-3-Clause BSD
 *
 * @version 0.6
 */

namespace Bugo\SimpleModMaker;

if (! defined('SMF'))
	die('No direct access...');

final class Integration
{
	/**
	 * Runs the mod's hooks
	 */
	public function hooks(): void
	{
		add_integration_function('integrate_user_info', self::class . '::userInfo#', false, __FILE__);
		add_integration_function('integrate_admin_areas', self::class . '::adminAreas#', false, __FILE__);
	}

	/**
	 * @hook integrate_user_info
	 */
	public function userInfo(): void
	{
		defined('SMM_NAME') || define('SMM_NAME', 'Simple Mod Maker');
	}

	/**
	 * @hook integrate_admin_areas
	 */
	public function adminAreas(array &$admin_areas): void
	{
		global $txt;

		loadLanguage('SimpleModMaker/');

		$admin_areas['config']['areas']['smm'] = [
			'label'       => SMM_NAME,
			'function'    => [$this, 'settings'],
			'icon'        => 'maintain',
			'subsections' => [
				'basic'     => [$txt['smm_basic']],
				'generator' => [$txt['smm_generator']],
			]
		];
	}

	/**
	 * Directs the admin to the proper page of settings for the Mod Maker
	 */
	public function settings(): void
	{
		global $context, $txt;

		loadTemplate('SimpleModMaker');

		require_once __DIR__ . '/vendor/autoload.php';
		require_once dirname(__DIR__) . '/ManageSettings.php';

		$this->prepareForumLanguages();

		$context['page_title'] = SMM_NAME;

		$subActions = [
			'basic'     => [$this, 'basicSettings'],
			'generator' => [new Handler, 'generator']
		];

		// Load up all the tabs...
		$context[$context['admin_menu_name']]['tab_data'] = [
			'title' => SMM_NAME,
			'description' => $txt['smm_desc'],
			'tabs' => [
				'basic'     => [],
				'generator' => [],
			]
		];

		loadGeneralSettingParameters($subActions, 'basic');

		call_helper($subActions[$context['sub_action']]);
	}

	/**
	 * Implements the basic settings of the Mod Maker
	 *
	 * @return void|array
	 */
	public function basicSettings(bool $return_config = false)
	{
		global $context, $txt, $scripturl;

		loadJavaScriptFile('simple_mod_maker/alpine.min.js', ['defer' => true]);

		require_once dirname(__DIR__) . '/ManageServer.php';

		$context['page_title'] .= ' - ' . $txt['smm_basic'];
		$context['post_url'] = $scripturl . '?action=admin;area=smm;sa=basic;save';

		$this->addDefaultSettings();
		$this->extendReadmeDesc();
		$this->prepareBbcEditor();

		$config_vars = [
			['title', 'smm_basic'],
			['text', 'smm_mod_author'],
			['email', 'smm_mod_email', 'label' => $txt['email']],
			['title', 'smm_readme'],
			['desc', 'smm_readme_desc'],
			['callback', 'smm_readme_editor'],
		];

		if ($return_config)
			return $config_vars;

		// Saving?
		if (isset($_GET['save'])) {
			checkSession();

			$save_vars = $config_vars;

			foreach ($context['languages'] as $lang) {
				if (isset($_POST['smm_readme_' . $lang['filename']])) {
					$_POST['smm_readme'][$lang['filename']] = $_POST['smm_readme_' . $lang['filename']];
				}
			}

			if (isset($_POST['smm_readme'])) {
				$_POST['smm_readme'] = json_encode($_POST['smm_readme']);

				$save_vars[] = ['large_text', 'smm_readme'];
			}

			saveDBSettings($save_vars);

			redirectexit('action=admin;area=smm;sa=basic');
		}

		prepareDBSettingContext($config_vars);
	}

	private function prepareForumLanguages(): void
	{
		global $context, $modSettings, $language, $user_info;

		getLanguages();

		$temp = $context['languages'];

		if (empty($modSettings['userLanguage'])) {
			$context['languages'] = ['english' => $temp['english']];

			if ($language !== 'english')
				$context['languages'][$language] = $temp[$language];
		}

		$context['languages'] = array_merge(
			[
				'english'              => $temp['english'],
				$user_info['language'] => $temp[$user_info['language']],
				$language              => $temp[$language]
			],
			$context['languages']
		);
	}

	private function addDefaultSettings(): void
	{
		global $modSettings, $user_info, $context, $txt;

		$addSettings = [];

		if (! isset($modSettings['smm_mod_author']))
			$addSettings['smm_mod_author'] = $user_info['name'];
		if (! isset($modSettings['smm_mod_email']))
			$addSettings['smm_mod_email'] = $user_info['email'];

		$readme = [];
		foreach ($context['languages'] as $lang) {
			loadLanguage('SimpleModMaker/', $lang['filename']);

			$readme[$lang['filename']] = $context['smm_readme'][$lang['filename']] ?? $txt['smm_readme_default'] ?? '';
		}

		$addSettings['smm_readme'] = json_encode($readme);

		updateSettings($addSettings);

		loadLanguage('SimpleModMaker/');
	}

	private function extendReadmeDesc(): void
	{
		global $txt;

		$variables = array_map(fn($k, $v) => "{<strong>$k</strong>} - $v", array_keys($txt['smm_readme_vars']), $txt['smm_readme_vars']);

		$txt['smm_readme_desc'] .= '<br><ul class="bbc_list"><li>' . implode('</li><li>', $variables) . '</ul>';

	}

	private function prepareBbcEditor(): void
	{
		global $context, $modSettings;

		require_once dirname(__DIR__) . '/Subs-Editor.php';

		$context['smm_readme'] = smf_json_decode($modSettings['smm_readme'] ?? '', true);

		$context['smm_readme_editor'] = [];

		foreach ($context['languages'] as $lang) {
			$editorOptions = [
				'id'           => 'smm_readme_' . $lang['filename'],
				'value'        => $context['smm_readme'][$lang['filename']] ?? '',
				'height'       => '150px',
				'width'        => '100%',
				'preview_type' => 2,
			];

			create_control_richedit($editorOptions);

			$context['smm_readme_editor'][$lang['filename']] = $editorOptions['id'];
		}
	}
}
