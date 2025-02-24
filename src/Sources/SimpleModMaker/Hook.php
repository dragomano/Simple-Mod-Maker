<?php declare(strict_types=1);

/**
 * @package Simple Mod Maker
 * @link https://github.com/dragomano/Simple-Mod-Maker
 * @author Bugo <bugo@dragomano.ru>
 * @copyright 2022-2024 Bugo
 * @license https://opensource.org/licenses/BSD-3-Clause BSD
 * @version 0.8
 */

namespace Bugo\SimpleModMaker;

class Hook
{
	public const ADMIN_AREAS = 'integrate_admin_areas';

	public const ADMIN_SEARCH = 'integrate_admin_search';

	public const CREDITS = 'integrate_credits';

	public const DAILY_MAINTENANCE = 'integrate_daily_maintenance';

	public const FORUM_STATS = 'integrate_forum_stats';

	public const GENERAL_MOD_SETTINGS = 'integrate_general_mod_settings';

	public const HELP_ADMIN = 'integrate_helpadmin';

	public const LOAD_THEME = 'integrate_load_theme';

	public const MODIFY_MODIFICATIONS = 'integrate_modify_modifications';

	public const POST_END = 'integrate_post_end';

	public const PRE_CSS_OUTPUT = 'integrate_pre_css_output';

	public const PRE_LOAD = 'integrate_pre_load';

	public const THEME_CONTEXT = 'integrate_theme_context';

	public const USER_INFO = 'integrate_user_info';

	public const WEEKLY_MAINTENANCE = 'integrate_weekly_maintenance';
}