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

class License
{
	public const LICENSES = [
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
			'short_name' => 'smm_license_own',
			'full_name'  => 'smm_license_name',
			'link'       => 'smm_license_link',
		]
	];

	public static function getAll(array $txt): array
	{
		$licenses = self::LICENSES;

		self::localizeOwn($licenses['own'], $txt);

		return $licenses;
	}

	private static function localizeOwn(array &$license, array $txt): void
	{
		if (isset($license['short_name']) && $license['short_name'] === 'smm_license_own') {
			$license['short_name'] = $txt['smm_license_own'];
			$license['full_name']  = $txt['smm_license_name'];
			$license['link']       = $txt['smm_license_link'];
		}
	}
}
