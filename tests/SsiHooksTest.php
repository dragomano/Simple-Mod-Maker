<?php declare(strict_types=1);

namespace Tests;

use Bugo\SimpleModMaker\Hooks\AbstractHook;
use Bugo\SimpleModMaker\Hooks\HookFactory;
use Bugo\SimpleModMaker\Hooks\SsiBoardNewsHook;
use Bugo\SimpleModMaker\Hooks\SsiBoardStatsHook;
use Bugo\SimpleModMaker\Hooks\SsiCalendarHook;
use Bugo\SimpleModMaker\Hooks\SsiQueryMembersHook;
use Bugo\SimpleModMaker\Hooks\SsiQueryPostsHook;
use Bugo\SimpleModMaker\Hooks\SsiRecentAttachmentsHook;
use Bugo\SimpleModMaker\Hooks\SsiRecentEventsHook;
use Bugo\SimpleModMaker\Hooks\SsiRecentPollHook;
use Bugo\SimpleModMaker\Hooks\SsiRecentTopicsHook;
use Bugo\SimpleModMaker\Hooks\SsiShowPollHook;
use Bugo\SimpleModMaker\Hooks\SsiTopBoardsHook;
use Bugo\SimpleModMaker\Hooks\SsiTopPosterHook;
use Bugo\SimpleModMaker\Hooks\SsiTopTopicsHook;
use Bugo\SimpleModMaker\Hooks\SsiWhosOnlineHook;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The SSI hooks only report the data of an SSI page: each of them takes the
 * parameter of its own SSI function and dumps it, so the generated skeleton
 * shows the developer what is available there.
 */
#[CoversClass(SsiBoardNewsHook::class)]
#[CoversClass(SsiBoardStatsHook::class)]
#[CoversClass(SsiCalendarHook::class)]
#[CoversClass(SsiQueryMembersHook::class)]
#[CoversClass(SsiQueryPostsHook::class)]
#[CoversClass(SsiRecentAttachmentsHook::class)]
#[CoversClass(SsiRecentEventsHook::class)]
#[CoversClass(SsiRecentPollHook::class)]
#[CoversClass(SsiRecentTopicsHook::class)]
#[CoversClass(SsiShowPollHook::class)]
#[CoversClass(SsiTopBoardsHook::class)]
#[CoversClass(SsiTopPosterHook::class)]
#[CoversClass(SsiTopTopicsHook::class)]
#[CoversClass(SsiWhosOnlineHook::class)]
class SsiHooksTest extends TestCase
{
	#[Test]
	#[DataProvider('hooks')]
	public function theGeneratedContentOfEveryHookIsStable(
		string $className,
		string $hookName,
		string $methodName,
		array $parameters,
		array $body
	): void {
		$hook = HookFactory::create($hookName, [], 'Demo', 'demo');

		$this->assertInstanceOf(AbstractHook::class, $hook);
		$this->assertInstanceOf($className, $hook);
		$this->assertSame($hookName, $hook->getName());
		$this->assertSame($methodName, $hook->getMethodName());
		$this->assertSame($parameters, $hook->getParameters());
		$this->assertSame('void', $hook->getReturnType());
		$this->assertSame($body, self::withoutPlatformLineEndings($hook->getBody()));

		// A hook with a single body serves every supported SMF version
		$this->assertNull($hook->getDualBody());
	}

	/**
	 * The generated bodies keep PHP_EOL as their line separator, so the
	 * platform-specific form is reduced to the common one before the
	 * comparison.
	 */
	private static function withoutPlatformLineEndings(array $lines): array
	{
		return array_map(static fn (string $line): string => str_replace(PHP_EOL, "\n", $line), $lines);
	}

	/**
	 * The expected content of each hook: the registered name, the generated
	 * method, the signature of that method and the body of the generated code.
	 */
	public static function hooks(): array
	{
		return [
			SsiBoardNewsHook::class => [
				SsiBoardNewsHook::class,
				'integrate_ssi_boardNews',
				'ssiBoardNews',
				[
					'return' => ['array', true],
				],
				[
					"echo '<pre>'. print_r(\$return, true) . '</pre>';",
				],
			],
			SsiBoardStatsHook::class => [
				SsiBoardStatsHook::class,
				'integrate_ssi_boardStats',
				'ssiBoardStats',
				[
					'totals' => ['array', true],
				],
				[
					"echo '<pre>'. print_r(\$totals, true) . '</pre>';",
				],
			],
			SsiCalendarHook::class => [
				SsiCalendarHook::class,
				'integrate_ssi_calendar',
				'ssiCalendar',
				[
					'return' => ['array', true],
					'eventOptions' => ['array'],
				],
				[
					"echo '<pre>'. print_r(\$return, true) . '</pre>';",
				],
			],
			SsiQueryMembersHook::class => [
				SsiQueryMembersHook::class,
				'integrate_ssi_queryMembers',
				'ssiQueryMembers',
				[
					'members' => ['array', true],
				],
				[
					"echo '<pre>'. print_r(\$members, true) . '</pre>';",
				],
			],
			SsiQueryPostsHook::class => [
				SsiQueryPostsHook::class,
				'integrate_ssi_queryPosts',
				'ssiQueryPosts',
				[
					'posts' => ['array', true],
				],
				[
					"echo '<pre>'. print_r(\$posts, true) . '</pre>';",
				],
			],
			SsiRecentAttachmentsHook::class => [
				SsiRecentAttachmentsHook::class,
				'integrate_ssi_recentAttachments',
				'ssiRecentAttachments',
				[
					'attachments' => ['array', true],
				],
				[
					"echo '<pre>'. print_r(\$attachments, true) . '</pre>';",
				],
			],
			SsiRecentEventsHook::class => [
				SsiRecentEventsHook::class,
				'integrate_ssi_recentEvents',
				'ssiRecentEvents',
				[
					'return' => ['array', true],
				],
				[
					"echo '<pre>'. print_r(\$return, true) . '</pre>';",
				],
			],
			SsiRecentPollHook::class => [
				SsiRecentPollHook::class,
				'integrate_ssi_recentPoll',
				'ssiRecentPoll',
				[
					'return' => ['array', true],
					'topPollInstead' => ['bool'],
				],
				[
					"echo '<pre>'. print_r(\$return, true) . '</pre>';",
				],
			],
			SsiRecentTopicsHook::class => [
				SsiRecentTopicsHook::class,
				'integrate_ssi_recentTopics',
				'ssiRecentTopics',
				[
					'posts' => ['array', true],
				],
				[
					"echo '<pre>'. print_r(\$posts, true) . '</pre>';",
				],
			],
			SsiShowPollHook::class => [
				SsiShowPollHook::class,
				'integrate_ssi_showPoll',
				'ssiShowPoll',
				[
					'return' => ['array', true],
				],
				[
					"echo '<pre>' . print_r(\$return, true) . '</pre>';",
				],
			],
			SsiTopBoardsHook::class => [
				SsiTopBoardsHook::class,
				'integrate_ssi_topBoards',
				'ssiTopBoards',
				[
					'boards' => ['array', true],
				],
				[
					"echo '<pre>'. print_r(\$boards, true) . '</pre>';",
				],
			],
			SsiTopPosterHook::class => [
				SsiTopPosterHook::class,
				'integrate_ssi_topPoster',
				'ssiTopPoster',
				[
					'return' => ['array', true],
				],
				[
					"echo '<pre>'. print_r(\$return, true) . '</pre>';",
				],
			],
			SsiTopTopicsHook::class => [
				SsiTopTopicsHook::class,
				'integrate_ssi_topTopics',
				'ssiTopTopics',
				[
					'topics' => ['array', true],
					'type' => ['string'],
				],
				[
					"echo '<pre>'. print_r(\$topics, true) . '</pre>';",
				],
			],
			SsiWhosOnlineHook::class => [
				SsiWhosOnlineHook::class,
				'integrate_ssi_whosOnline',
				'ssiWhosOnline',
				[
					'return' => ['array', true],
				],
				[
					"echo '<pre>'. print_r(\$return, true) . '</pre>';",
				],
			],
		];
	}
}
