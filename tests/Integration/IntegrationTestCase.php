<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Tests\Integration;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\HttpFactory;
use Minhyung\Mybox\ClientConfig;
use Minhyung\Mybox\Exception\MyboxException;
use Minhyung\Mybox\Http\RetryPolicy;
use Minhyung\Mybox\MyboxClient;
use PHPUnit\Framework\TestCase;

/**
 * Base class for tests that talk to a real MYBOX account.
 *
 * The token comes from `MYBOX_PAT`, either exported or written to a `.env`
 * file at the project root. Without one, every integration test is skipped.
 *
 * All work happens inside a single sandbox folder that is created before the
 * class runs and erased — trash included — afterwards, so a failing test can
 * never leave debris among the account's real files.
 */
abstract class IntegrationTestCase extends TestCase
{
    /** Sandbox folder created at the drive root. */
    public const SANDBOX = '__php_mybox_sdk_test__';

    protected static ?MyboxClient $mybox = null;
    protected static ?string $sandboxId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $token = self::token();

        if ($token === null) {
            return;
        }

        self::$mybox = new MyboxClient(
            new ClientConfig($token, retryPolicy: new RetryPolicy(maxAttempts: 3)),
            new GuzzleClient(['http_errors' => false, 'timeout' => 120]),
            new HttpFactory(),
            new HttpFactory(),
        );

        self::$sandboxId = self::$mybox->files()->createFolder(self::SANDBOX)->resourceId;
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$mybox !== null && self::$sandboxId !== null) {
            try {
                self::$mybox->files()->delete(self::$sandboxId);
                self::$mybox->trash()->purge(self::$sandboxId);
            } catch (MyboxException $e) {
                fwrite(STDERR, sprintf(
                    "\nCould not clean up the \"%s\" sandbox folder: %s\nRemove it by hand.\n",
                    self::SANDBOX,
                    $e->getMessage(),
                ));
            }
        }

        self::$mybox = null;
        self::$sandboxId = null;

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (self::$mybox === null) {
            self::markTestSkipped('Set MYBOX_PAT to run the integration suite.');
        }
    }

    protected function mybox(): MyboxClient
    {
        self::assertNotNull(self::$mybox);

        return self::$mybox;
    }

    protected function sandboxId(): string
    {
        self::assertNotNull(self::$sandboxId);

        return self::$sandboxId;
    }

    /**
     * Reads the token from the environment, falling back to a `.env` file so a
     * developer never has to put it in shell history.
     */
    private static function token(): ?string
    {
        $token = getenv('MYBOX_PAT');

        if (is_string($token) && trim($token) !== '') {
            return trim($token);
        }

        $envFile = dirname(__DIR__, 2) . '/.env';

        if (!is_readable($envFile)) {
            return null;
        }

        foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
            if (preg_match('/^\s*MYBOX_PAT\s*=\s*(.+?)\s*$/', $line, $m) === 1) {
                return trim($m[1], "\"'");
            }
        }

        return null;
    }
}
