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

namespace Bugo\SimpleModMaker;

if (! defined('SMF'))
	die('No direct access...');

/**
 * Keeps the serialization contexts apart.
 *
 * Input values are stored as normalized source text. Each consumer picks the
 * strategy matching its output context instead of relying on a single global
 * htmlspecialchars() pass:
 *
 * - PHP source literals: var_export() at the call site
 * - PHP docblocks: phpComment() (user text cannot terminate the comment)
 * - inline <script> payloads: json() (HTML/script safe)
 * - HTML text and attributes: html()
 */
final class Serializer
{
	private const JSON_FLAGS = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
		| JSON_INVALID_UTF8_SUBSTITUTE;

	public static function phpComment(string $value): string
	{
		// Drop control characters and neutralize the docblock terminator so that
		// arbitrary text cannot close the comment and become executable code.
		$value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value) ?? '';

		return str_replace('*/', '* /', $value);
	}

	public static function json(mixed $value): string
	{
		return (string) json_encode($value, self::JSON_FLAGS);
	}

	public static function html(string $value): string
	{
		return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE);
	}
}
