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
		return lcfirst(str_replace(' ', '', ucwords(strtr($this->name, ['integrate' => '', '_' => ' ']))));
	}

	public function getReturnType(): string
	{
		return 'void';
	}
}
