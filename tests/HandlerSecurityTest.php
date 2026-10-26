<?php declare(strict_types=1);

namespace Tests;

use Bugo\SimpleModMaker\Handler;
use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

#[CoversMethod(Handler::class, 'validateSubmission')]
#[CoversMethod(Handler::class, 'registerFormSequence')]
class HandlerSecurityTest extends TestCase
{
	private Handler $handler;

	/** @var array<string, mixed> */
	private array $globals = [];

	protected function setUp(): void
	{
		$this->handler = new Handler();

		$this->resetRequest();
	}

	/**
	 */
	public function testGetRequestIsNotTreatedAsSubmission(): void
	{
		$this->invokeValidate();

		$this->assertSame([], $_SESSION['forms']);
		$this->assertArrayNotHasKey('smm_form_sequences', $_SESSION);
	}

	public function testRegisterFormSequenceKeepsSeveralFormsUsable(): void
	{
		$first  = $this->registerForm();
		$second = $this->registerForm();

		$this->assertNotSame($first, $second);
		$this->assertArrayHasKey($first, $_SESSION['smm_form_sequences']);
		$this->assertArrayHasKey($second, $_SESSION['smm_form_sequences']);
	}

	public function testSubmissionWithoutSessionCheckIsRejected(): void
	{
		$sequence = $this->registerForm();

		$this->setSubmission($sequence, session: false);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('session_timeout');

		$this->invokeValidate();
	}

	public function testSubmissionWithWrongSessionCheckIsRejected(): void
	{
		$sequence = $this->registerForm();

		$this->setSubmission($sequence, session: 'wrong');

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('session_timeout');

		$this->invokeValidate();
	}

	public function testSubmissionWithAnySequenceIsAccepted(): void
	{
		$this->setSubmission('17000001');

		$this->invokeValidate();

		$this->assertSame([], $_SESSION['forms']);
	}

	public function testRepeatedSubmissionIsAccepted(): void
	{
		$this->setSubmission('17000001');
		$this->invokeValidate();

		$this->setSubmission('17000001');
		$this->invokeValidate();

		$this->assertSame([], $_SESSION['forms']);
	}

	public function testReplacementFormAfterFailedSubmissionRemainsUsable(): void
	{
		$first  = $this->registerForm();
		$second = $this->registerForm();

		$this->setSubmission($first);
		$this->invokeValidate();

		$replacement = $this->registerForm(); // A new form is rendered after an error

		$this->setSubmission($second);
		$this->invokeValidate();

		$this->assertArrayHasKey($replacement, $_SESSION['smm_form_sequences']);
	}

	public function testPendingFormSequencesAreBounded(): void
	{
		for ($i = 0; $i < 30; $i++) {
			$this->registerForm();
		}

		$this->assertCount(20, $_SESSION['smm_form_sequences']);
	}

	private function invokeValidate(): void
	{
		$method = new ReflectionMethod($this->handler, 'validateSubmission');

		$method->invoke($this->handler);
	}

	private function registerForm(): string
	{
		$method = new ReflectionMethod($this->handler, 'registerFormSequence');

		$method->invoke($this->handler);

		return (string) $this->globals['context']['form_sequence_number'];
	}

	private function setSubmission(mixed $sequence, bool|string $session = true): void
	{
		global $sc;

		$_POST['save'] = '';

		if ($session !== false) {
			$_POST[$_SESSION['session_var']] = $session === true ? $sc : $session;
		}

		if ($sequence !== null) {
			$_POST['seqnum']    = $sequence;
			$_REQUEST['seqnum'] = $sequence;
		}
	}

	private function resetRequest(): void
	{
		global $context, $sc, $modSettings, $boardurl;

		$this->globals['context'] = &$context;

		$sc          = 'test-session-check';
		$modSettings = ['strictSessionCheck' => true];
		$boardurl    = 'https://example.test';

		$context = [];

		$_SESSION = [
			'session_var' => 'test_session_var',
			'USER_AGENT'  => 'handler-security-test',
			'forms'       => [],
		];

		$_SERVER['HTTP_USER_AGENT'] = 'handler-security-test';

		unset($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_X_MOZ']);

		$_GET = $_POST = $_REQUEST = [];
	}
}
