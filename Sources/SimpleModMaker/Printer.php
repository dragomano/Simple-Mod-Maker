<?php

declare(strict_types=1);

/**
 * Printer.php
 *
 * @package Simple Mod Maker
 * @link https://github.com/dragomano/Simple-Mod-Maker
 * @author Bugo <bugo@dragomano.ru>
 * @copyright 2022-2024 Bugo
 * @license https://opensource.org/licenses/BSD-3-Clause BSD
 *
 * @version 0.7.2
 */

namespace Bugo\SimpleModMaker;

use Nette\PhpGenerator\Printer as BasePrinter;

if (! defined('SMF'))
	die('No direct access...');

final class Printer extends BasePrinter
{
	public $indentation = "\t";

	public $linesBetweenProperties = 1;

	public $linesBetweenMethods = 1;

	public $returnTypeColon = ': ';
}
