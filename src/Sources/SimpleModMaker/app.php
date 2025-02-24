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

use Bugo\SimpleModMaker\Integration;

if (! defined('SMF'))
	die('No direct access...');

defined('SMM_NAME') || define('SMM_NAME', 'Simple Mod Maker');
defined('SMM_MODNAME_DEFAULT') || define('SMM_MODNAME_DEFAULT', 'My New Mod');
defined('SMM_FILENAME_PATTERN') || define('SMM_FILENAME_PATTERN', '^(?:Class-)?[A-Z][a-zA-Z]+$');

require_once __DIR__ . '/vendor/autoload.php';

new Integration();
