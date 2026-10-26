<?php declare(strict_types=1);

namespace Tests;

use Bugo\SimpleModMaker\Hooks\AbstractHook;
use Bugo\SimpleModMaker\Hooks\AdminSearchHook;
use Bugo\SimpleModMaker\Hooks\AttachmentRemoveHook;
use Bugo\SimpleModMaker\Hooks\AttachmentsBrowseHook;
use Bugo\SimpleModMaker\Hooks\BbcButtonsHook;
use Bugo\SimpleModMaker\Hooks\BeforeCreateTopicHook;
use Bugo\SimpleModMaker\Hooks\CreatePostHook;
use Bugo\SimpleModMaker\Hooks\DisplayButtonsHook;
use Bugo\SimpleModMaker\Hooks\DisplayTopicHook;
use Bugo\SimpleModMaker\Hooks\HookFactory;
use Bugo\SimpleModMaker\Hooks\LoadMessageIconsHook;
use Bugo\SimpleModMaker\Hooks\LoadPermissionsHook;
use Bugo\SimpleModMaker\Hooks\ManageBoardsHook;
use Bugo\SimpleModMaker\Hooks\ManageHelpHook;
use Bugo\SimpleModMaker\Hooks\MessageIndexHook;
use Bugo\SimpleModMaker\Hooks\ModifyBasicSettingsHook;
use Bugo\SimpleModMaker\Hooks\ModifyBoardsSettingsHook;
use Bugo\SimpleModMaker\Hooks\ModifyModificationsHook;
use Bugo\SimpleModMaker\Hooks\ModifyPostHook;
use Bugo\SimpleModMaker\Hooks\PostParsebbcHook;
use Bugo\SimpleModMaker\Hooks\PostQuickbuttonsHook;
use Bugo\SimpleModMaker\Hooks\PreLogStatsHook;
use Bugo\SimpleModMaker\Hooks\PrepareDisplayContextHook;
use Bugo\SimpleModMaker\Hooks\QueryMessageHook;
use Bugo\SimpleModMaker\Hooks\RegisterCheckHook;
use Bugo\SimpleModMaker\Hooks\RemoveAttachmentsHook;
use Bugo\SimpleModMaker\Hooks\RepairAttachmentsNomsgHook;
use Bugo\SimpleModMaker\Hooks\SceditorOptionsHook;
use Bugo\SimpleModMaker\Hooks\SimpleActionsHook;
use Bugo\SimpleModMaker\Hooks\UpdateSettingsFileHook;
use Bugo\SimpleModMaker\Hooks\XmlDataHook;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Every hook that renders fixed code: the registered name, the generated
 * method, the parameters of that method and the body lines are pinned here,
 * because both the signature and the body end up in the packages the users
 * distribute.
 */
#[CoversClass(AdminSearchHook::class)]
#[CoversClass(AttachmentRemoveHook::class)]
#[CoversClass(AttachmentsBrowseHook::class)]
#[CoversClass(BbcButtonsHook::class)]
#[CoversClass(BeforeCreateTopicHook::class)]
#[CoversClass(CreatePostHook::class)]
#[CoversClass(DisplayButtonsHook::class)]
#[CoversClass(DisplayTopicHook::class)]
#[CoversClass(LoadMessageIconsHook::class)]
#[CoversClass(LoadPermissionsHook::class)]
#[CoversClass(ManageBoardsHook::class)]
#[CoversClass(ManageHelpHook::class)]
#[CoversClass(MessageIndexHook::class)]
#[CoversClass(ModifyBasicSettingsHook::class)]
#[CoversClass(ModifyBoardsSettingsHook::class)]
#[CoversClass(ModifyModificationsHook::class)]
#[CoversClass(ModifyPostHook::class)]
#[CoversClass(PostParsebbcHook::class)]
#[CoversClass(PostQuickbuttonsHook::class)]
#[CoversClass(PreLogStatsHook::class)]
#[CoversClass(PrepareDisplayContextHook::class)]
#[CoversClass(QueryMessageHook::class)]
#[CoversClass(RegisterCheckHook::class)]
#[CoversClass(RemoveAttachmentsHook::class)]
#[CoversClass(RepairAttachmentsNomsgHook::class)]
#[CoversClass(SceditorOptionsHook::class)]
#[CoversClass(SimpleActionsHook::class)]
#[CoversClass(UpdateSettingsFileHook::class)]
#[CoversClass(XmlDataHook::class)]
class HookBodiesTest extends TestCase
{
	#[Test]
	#[DataProvider('hooks')]
	public function theGeneratedContentOfEveryHookIsStable(
		string $className,
		string $hookName,
		string $methodName,
		array $parameters,
		array $body
	): void {
		$hook = HookFactory::create($hookName, [], 'Demo', 'demo');

		$this->assertInstanceOf(AbstractHook::class, $hook);
		$this->assertInstanceOf($className, $hook);
		$this->assertSame($hookName, $hook->getName());
		$this->assertSame($methodName, $hook->getMethodName());
		$this->assertSame($parameters, $hook->getParameters());
		$this->assertSame('void', $hook->getReturnType());
		$this->assertSame($body, self::withoutPlatformLineEndings($hook->getBody()));

		// A hook with a single body serves every supported SMF version
		$this->assertNull($hook->getDualBody());
	}

	/**
	 * The generated bodies keep PHP_EOL as their line separator, so the
	 * platform-specific form is reduced to the common one before the
	 * comparison.
	 */
	private static function withoutPlatformLineEndings(array $lines): array
	{
		return array_map(static fn (string $line): string => str_replace(PHP_EOL, "\n", $line), $lines);
	}

	/**
	 * The expected content of each hook: the registered name, the generated
	 * method, the signature of that method and the body of the generated code.
	 */
	public static function hooks(): array
	{
		return [
			AdminSearchHook::class => [
				AdminSearchHook::class,
				'integrate_admin_search',
				'adminSearch',
				[
					'language_files' => ['array', true],
					'include_files' => ['array', true],
					'settings_search' => ['array', true],
				],
				[
					"\$language_files[] = 'Demo';",
					"\$settings_search[] = [[\$this, 'settings'], 'area=modsettings;sa=demo'];",
				],
			],
			AttachmentRemoveHook::class => [
				AttachmentRemoveHook::class,
				'integrate_attachment_remove',
				'attachmentRemove',
				[
					'filesRemoved' => ['array', true],
					'attachments' => ['array'],
				],
				[],
			],
			AttachmentsBrowseHook::class => [
				AttachmentsBrowseHook::class,
				'integrate_attachments_browse',
				'attachmentsBrowse',
				[
					'listOptions' => ['array', true],
					'titles' => ['array', true],
					'list_title' => ['string', true],
				],
				[],
			],
			BbcButtonsHook::class => [
				BbcButtonsHook::class,
				'integrate_bbc_buttons',
				'bbcButtons',
				[
					'buttons' => ['array', true],
					'editor_tag_map' => ['array', true],
				],
				[
					'$buttons[] = [',
					"\t'code' => 'button_code',",
					"\t'description' => 'button desc'",
					"];\n",
					'//$var_dump($editor_tag_map);',
				],
			],
			BeforeCreateTopicHook::class => [
				BeforeCreateTopicHook::class,
				'integrate_before_create_topic',
				'beforeCreateTopic',
				[
					'msgOptions' => ['array', true],
					'topicOptions' => ['array', true],
					'posterOptions' => ['array', true],
					'topic_columns' => ['array', true],
					'topic_parameters' => ['array', true],
				],
				[],
			],
			CreatePostHook::class => [
				CreatePostHook::class,
				'integrate_create_post',
				'createPost',
				[
					'msgOptions' => ['array', true],
					'topicOptions' => ['array', true],
					'posterOptions' => ['array', true],
					'message_columns' => ['array', true],
					'message_parameters' => ['array', true],
				],
				[
					'// Add your code for post creation here',
					'// You can modify $msgOptions, $topicOptions, $posterOptions',
					'// Add columns to $message_columns array',
					'// Add parameters to $message_parameters array',
				],
			],
			DisplayButtonsHook::class => [
				DisplayButtonsHook::class,
				'integrate_display_buttons',
				'displayButtons',
				[
					'buttons' => ['array', true],
				],
				[
					'// Add your display buttons here',
					'// You can modify or add buttons to the $buttons array',
				],
			],
			DisplayTopicHook::class => [
				DisplayTopicHook::class,
				'integrate_display_topic',
				'displayTopic',
				[
					'topic_selects' => ['array', true],
					'topic_tables' => ['array', true],
					'topic_parameters' => ['array', true],
				],
				[
					'// Add your topic display modifications here',
					'// You can modify database queries for topic display',
				],
			],
			LoadMessageIconsHook::class => [
				LoadMessageIconsHook::class,
				'integrate_load_message_icons',
				'loadMessageIcons',
				[
					'icons' => ['array', true],
				],
				[
					'//var_dump($icons);',
				],
			],
			LoadPermissionsHook::class => [
				LoadPermissionsHook::class,
				'integrate_load_permissions',
				'loadPermissions',
				[
					'permissionGroups' => ['array', true],
					'permissionList' => ['array', true],
					'leftPermissionGroups' => ['array', true],
					'hiddenPermissions' => ['array', true],
					'relabelPermissions' => ['array', true],
				],
				[
					"\$permissionGroups['membergroup']['simple'] = ['demo'];",
					"\$permissionGroups['membergroup']['classic'] = ['demo'];\n",
					"\$permissionList['membergroup']['example_permission'] = [false, 'demo', 'demo'];",
				],
			],
			ManageBoardsHook::class => [
				ManageBoardsHook::class,
				'integrate_manage_boards',
				'manageBoards',
				[
					'subActions' => ['array', true],
				],
				[
					'//$var_dump($subActions);',
				],
			],
			ManageHelpHook::class => [
				ManageHelpHook::class,
				'integrate_manage_help',
				'manageHelp',
				[
					'subActions' => ['array', true],
				],
				[],
			],
			MessageIndexHook::class => [
				MessageIndexHook::class,
				'integrate_message_index',
				'messageIndex',
				[
					'message_index_selects' => ['array', true],
					'message_index_tables' => ['array', true],
					'message_index_parameters' => ['array', true],
					'message_index_wheres' => ['array', true],
					'topic_ids' => ['array', true],
					'message_index_topic_wheres' => ['array', true],
				],
				[
					'// Add your message index modifications here',
					'// You can modify database queries for message index',
				],
			],
			ModifyBasicSettingsHook::class => [
				ModifyBasicSettingsHook::class,
				'integrate_modify_basic_settings',
				'modifyBasicSettings',
				[
					'config_vars' => ['array', true],
				],
				[],
			],
			ModifyBoardsSettingsHook::class => [
				ModifyBoardsSettingsHook::class,
				'integrate_modify_boards_settings',
				'modifyBoardsSettings',
				[
					'config_vars' => ['array', true],
				],
				[
					'//$var_dump($config_vars);',
				],
			],
			ModifyModificationsHook::class => [
				ModifyModificationsHook::class,
				'integrate_modify_modifications',
				'modifyModifications',
				[
					'subActions' => ['array', true],
				],
				[
					"\$subActions['demo'] = [\$this, 'settings'];",
				],
			],
			ModifyPostHook::class => [
				ModifyPostHook::class,
				'integrate_modify_post',
				'modifyPost',
				[
					'messages_columns' => ['array', true],
					'update_parameters' => ['array', true],
					'msgOptions' => ['array', true],
					'topicOptions' => ['array', true],
					'posterOptions' => ['array', true],
					'messageInts' => ['array', true],
				],
				[],
			],
			PostParsebbcHook::class => [
				PostParsebbcHook::class,
				'integrate_post_parsebbc',
				'postParsebbc',
				[
					'message' => ['string', true],
					'smileys' => ['boolean', true],
					'cache_id' => ['string', true],
					'parse_tags' => ['array', true],
				],
				[],
			],
			PostQuickbuttonsHook::class => [
				PostQuickbuttonsHook::class,
				'integrate_post_quickbuttons',
				'postQuickbuttons',
				[
					'list_items' => ['array', true],
				],
				[
					'/*',
					"\$list_items['my_button'] = [",
					"\t'label' => 'Label text',",
					"\t'href'  => 'https://site.domain',",
					"\t'icon'  => 'home',",
					"\t'show'  => true",
					'];',
					'*/',
				],
			],
			PreLogStatsHook::class => [
				PreLogStatsHook::class,
				'integrate_pre_log_stats',
				'preLogStats',
				[
					'no_stat_actions' => ['array', true],
				],
				[
					'//$var_dump($no_stat_actions);',
				],
			],
			PrepareDisplayContextHook::class => [
				PrepareDisplayContextHook::class,
				'integrate_prepare_display_context',
				'prepareDisplayContext',
				[
					'output' => ['array', true],
					'message' => ['array', true],
					'counter' => ['int'],
				],
				[],
			],
			QueryMessageHook::class => [
				QueryMessageHook::class,
				'integrate_query_message',
				'queryMessage',
				[
					'msg_selects' => ['array', true],
					'msg_tables' => ['array', true],
					'msg_parameters' => ['array', true],
				],
				[],
			],
			RegisterCheckHook::class => [
				RegisterCheckHook::class,
				'integrate_register_check',
				'registerCheck',
				[
					'regOptions' => ['array', true],
					'reg_errors' => ['array', true],
				],
				[],
			],
			RemoveAttachmentsHook::class => [
				RemoveAttachmentsHook::class,
				'integrate_remove_attachments',
				'removeAttachments',
				[
					'attach' => ['array'],
				],
				[],
			],
			RepairAttachmentsNomsgHook::class => [
				RepairAttachmentsNomsgHook::class,
				'integrate_repair_attachments_nomsg',
				'repairAttachmentsNomsg',
				[
					'ignore_ids' => ['array', true],
					'step' => ['int'],
					'next_step' => ['int'],
				],
				[],
			],
			SceditorOptionsHook::class => [
				SceditorOptionsHook::class,
				'integrate_sceditor_options',
				'sceditorOptions',
				[
					'sce_options' => ['array', true],
				],
				[
					"//\$sce_options['new_option'] = 'value';",
				],
			],
			SimpleActionsHook::class => [
				SimpleActionsHook::class,
				'integrate_simple_actions',
				'simpleActions',
				[
					'simpleActions' => ['array', true],
					'simpleAreas' => ['array', true],
					'simpleSubActions' => ['array', true],
					'extraParams' => ['array', true],
					'xmlActions' => ['array', true],
				],
				[
					"// \$simpleActions[] = 'example_simple_action';",
					"// \$xmlActions[] = 'example_xml_action';",
				],
			],
			UpdateSettingsFileHook::class => [
				UpdateSettingsFileHook::class,
				'integrate_update_settings_file',
				'updateSettingsFile',
				[
					'settings_defs' => ['array', true],
				],
				[
					"\$settings_defs['your_variable'] = [",
					"\t'text' => implode(\"\\n\", [",
					"\t\t'/**',",
					"\t\t' * Description',",
					"\t\t' *',",
					"\t\t' * @var string',",
					"\t\t' */',",
					"\t]),",
					"\t'default' => 'default_value',",
					"\t'type' => 'string'",
					'];',
				],
			],
			XmlDataHook::class => [
				XmlDataHook::class,
				'integrate_xml_data',
				'xmlData',
				[
					'xml_data' => ['array', true],
					'feed_meta' => ['array', true],
					'namespaces' => ['array', true],
					'extraFeedTags' => ['array', true],
					'forceCdataKeys' => ['array', true],
					'nsKeys' => ['array', true],
					'xml_format' => ['string'],
					'sa' => ['string'],
					'doctype' => ['string', true],
				],
				[],
			],
		];
	}
}
