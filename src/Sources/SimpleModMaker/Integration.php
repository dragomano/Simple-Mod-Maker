<?php declare(strict_types=1);

/**
 * @package Simple Mod Maker
 * @link https://github.com/dragomano/Simple-Mod-Maker
 * @author Bugo <bugo@dragomano.ru>
 * @copyright 2022-2024 Bugo
 * @license https://opensource.org/licenses/BSD-3-Clause BSD
 *
 * @version 0.8
 */

namespace Bugo\SimpleModMaker;

if (! defined('SMF'))
	die('No direct access...');

final class Integration
{
	public function __construct()
	{
		add_integration_function(
			Hook::ADMIN_AREAS,
			self::class . '::adminAreas#',
			false,
			__FILE__
		);
	}

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

	public function settings(): void
	{
		global $context, $txt;

		loadTemplate('SimpleModMaker');

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
	 * @return void|array
	 */
	public function basicSettings(bool $return_config = false)
	{
		global $context, $txt, $scripturl;

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

			foreach ($context['smm_languages'] as $lang) {
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
		global $modSettings, $context, $user_info, $language;

		$temp = getLanguages();

		if (empty($modSettings['userLanguage'])) {
			$context['smm_languages'] = ['english' => $temp['english']];

			if ($language !== 'english') {
				$context['smm_languages'][$language] = $temp[$language];
			}

			return;
		}

		$context['smm_languages'] = array_merge(
			[
				'english'              => $temp['english'],
				$user_info['language'] => $temp[$user_info['language']],
				$language              => $temp[$language]
			],
			$temp
		);
	}

	private function addDefaultSettings(): void
	{
		global $modSettings, $user_info, $context, $txt;

		$addSettings = [];

		if (! isset($modSettings['smm_mod_author'])) {
			$addSettings['smm_mod_author'] = $user_info['name'];
		}
		if (! isset($modSettings['smm_mod_email'])) {
			$addSettings['smm_mod_email'] = $user_info['email'];
		}

		$readme = [];
		foreach ($context['smm_languages'] as $lang) {
			loadLanguage('SimpleModMaker/', $lang['filename'], false, true);

			$readme[$lang['filename']] = $txt['smm_readme_default'] ?? '';
		}

		if (! isset($modSettings['smm_readme'])) {
			$addSettings['smm_readme'] = json_encode($readme);
		}

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

		foreach ($context['smm_languages'] as $lang) {
			$editorOptions = [
				'id'                 => 'smm_readme_' . $lang['filename'],
				'value'              => $context['smm_readme'][$lang['filename']] ?? '',
				'height'             => '150px',
				'width'              => '100%',
				'disable_smiley_box' => true,
			];

			create_control_richedit($editorOptions);

			$context['smm_readme_editor'][$lang['filename']] = $editorOptions['id'];
		}
	}
}
