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

namespace Bugo\SimpleModMaker\Hooks;

class XmlDataHook extends AbstractHook
{
	protected function defineName(): string
	{
		return 'integrate_xml_data';
	}

	public function getParameters(): array
	{
		return [
			'xml_data'       => ['array', true],
			'feed_meta'      => ['array', true],
			'namespaces'     => ['array', true],
			'extraFeedTags'  => ['array', true],
			'forceCdataKeys' => ['array', true],
			'nsKeys'         => ['array', true],
			'xml_format'     => ['string'],
			'sa'             => ['string'],
			'doctype'        => ['string', true],
		];
	}

	public function getBody(): array
	{
		return [];
	}
}
