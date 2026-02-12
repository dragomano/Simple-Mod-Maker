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

use Nette\PhpGenerator\Printer as BasePrinter;

if (! defined('SMF'))
	die('No direct access...');

final class Printer extends BasePrinter
{
	public string $indentation = "\t";

	public int $linesBetweenProperties = 1;

	public int $linesBetweenMethods = 1;

	public string $returnTypeColon = ': ';
}
