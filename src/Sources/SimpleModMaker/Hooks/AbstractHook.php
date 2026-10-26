<?php declare(strict_types=1);

/**
 * @package Simple Mod Maker
 * @link https://github.com/dragomano/Simple-Mod-Maker
 * @author Bugo <bugo@dragomano.ru>
 * @copyright 2022-2026 Bugo
 * @license https://opensource.org/licenses/BSD-3-Clause BSD
 */

namespace Bugo\SimpleModMaker\Hooks;

use Bugo\SimpleModMaker\Hook;

abstract class AbstractHook implements HookInterface
{
	protected string $name;

	public function __construct(protected array $context, protected string $classname, protected string $snakeName)
	{
		$this->name = $this->defineName();
	}

	abstract protected function defineName(): string;

	abstract public function getParameters(): array;

	abstract public function getBody(): array;

	public function getName(): string
	{
		return $this->name;
	}

	public function getMethodName(): string
	{
		return Hook::toMethodName($this->name);
	}

	public function getReturnType(): string
	{
		return 'void';
	}

	/**
	 * Both body variants for a mod built for both SMF versions at once.
	 * Null means the body is identical for all supported versions.
	 *
	 * @return array{legacy: list<string>, modern: list<string>}|null
	 */
	public function getDualBody(): ?array
	{
		return null;
	}

	/**
	 * Whether the generated code must use the modern API of SMF 3.0.
	 */
	protected function isModernTarget(): bool
	{
		return $this->context['smm_skeleton']['smf_target_version'] === '3.0';
	}
}
