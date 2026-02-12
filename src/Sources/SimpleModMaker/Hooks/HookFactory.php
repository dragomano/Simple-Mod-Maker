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

namespace Bugo\SimpleModMaker\Hooks;

class HookFactory
{
	private const HOOK_MAPPINGS = [
		'integrate_actions'                        => ActionsHook::class,
		'integrate_admin_areas'                    => AdminAreasHook::class,
		'integrate_admin_search'                   => AdminSearchHook::class,
		'integrate_alert_types'                    => AlertTypesHook::class,
		'integrate_attachment_remove'              => AttachmentRemoveHook::class,
		'integrate_attachments_browse'             => AttachmentsBrowseHook::class,
		'integrate_autoload'                       => AutoloadHook::class,
		'integrate_bbc_buttons'                    => BbcButtonsHook::class,
		'integrate_bbc_codes'                      => BbcCodesHook::class,
		'integrate_before_create_topic'            => BeforeCreateTopicHook::class,
		'integrate_buffer'                         => BufferHook::class,
		'integrate_create_post'                    => CreatePostHook::class,
		'integrate_credits'                        => CreditsHook::class,
		'integrate_daily_maintenance'              => DailyMaintenanceHook::class,
		'integrate_display_buttons'                => DisplayButtonsHook::class,
		'integrate_display_topic'                  => DisplayTopicHook::class,
		'integrate_download_request'               => DownloadRequestHook::class,
		'integrate_fetch_alerts'                   => FetchAlertsHook::class,
		'integrate_general_mod_settings'           => GeneralModSettingsHook::class,
		'integrate_load_illegal_guest_permissions' => LoadIllegalGuestPermissionsHook::class,
		'integrate_load_message_icons'             => LoadMessageIconsHook::class,
		'integrate_load_permissions'               => LoadPermissionsHook::class,
		'integrate_load_theme'                     => LoadThemeHook::class,
		'integrate_manage_boards'                  => ManageBoardsHook::class,
		'integrate_manage_help'                    => ManageHelpHook::class,
		'integrate_menu_buttons'                   => MenuButtonsHook::class,
		'integrate_message_index'                  => MessageIndexHook::class,
		'integrate_modify_basic_settings'          => ModifyBasicSettingsHook::class,
		'integrate_modify_boards_settings'         => ModifyBoardsSettingsHook::class,
		'integrate_modify_modifications'           => ModifyModificationsHook::class,
		'integrate_modify_post'                    => ModifyPostHook::class,
		'integrate_post_parsebbc'                  => PostParsebbcHook::class,
		'integrate_post_quickbuttons'              => PostQuickbuttonsHook::class,
		'integrate_pre_log_stats'                  => PreLogStatsHook::class,
		'integrate_prepare_display_context'        => PrepareDisplayContextHook::class,
		'integrate_query_message'                  => QueryMessageHook::class,
		'integrate_register_check'                 => RegisterCheckHook::class,
		'integrate_remove_attachments'             => RemoveAttachmentsHook::class,
		'integrate_repair_attachments_nomsg'       => RepairAttachmentsNomsgHook::class,
		'integrate_sceditor_options'               => SceditorOptionsHook::class,
		'integrate_simple_actions'                 => SimpleActionsHook::class,
		'integrate_ssi_boardNews'                  => SsiBoardNewsHook::class,
		'integrate_ssi_boardStats'                 => SsiBoardStatsHook::class,
		'integrate_ssi_calendar'                   => SsiCalendarHook::class,
		'integrate_ssi_queryMembers'               => SsiQueryMembersHook::class,
		'integrate_ssi_queryPosts'                 => SsiQueryPostsHook::class,
		'integrate_ssi_recentAttachments'          => SsiRecentAttachmentsHook::class,
		'integrate_ssi_recentEvents'               => SsiRecentEventsHook::class,
		'integrate_ssi_recentPoll'                 => SsiRecentPollHook::class,
		'integrate_ssi_recentTopics'               => SsiRecentTopicsHook::class,
		'integrate_ssi_showPoll'                   => SsiShowPollHook::class,
		'integrate_ssi_topBoards'                  => SsiTopBoardsHook::class,
		'integrate_ssi_topPoster'                  => SsiTopPosterHook::class,
		'integrate_ssi_topTopics'                  => SsiTopTopicsHook::class,
		'integrate_ssi_whosOnline'                 => SsiWhosOnlineHook::class,
	];

	public static function create(
		string $hookName,
		array $context = [],
		string $classname = '',
		string $snakeName = ''
	): ?HookInterface
	{
		if (! array_key_exists($hookName, self::HOOK_MAPPINGS)) {
			return null;
		}

		$hookClass = self::HOOK_MAPPINGS[$hookName];

		return new $hookClass($context, $classname, $snakeName);
	}

	public static function getAvailableHooks(): array
	{
		return array_keys(self::HOOK_MAPPINGS);
	}
}
