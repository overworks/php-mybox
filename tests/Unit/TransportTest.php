<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Tests\Unit;

use GuzzleHttp\Psr7\Request;
use Minhyung\Mybox\ClientConfig;
use Minhyung\Mybox\Exception\ApiException;
use Minhyung\Mybox\Exception\BadRequestException;
use Minhyung\Mybox\Exception\ConflictException;
use Minhyung\Mybox\Exception\ForbiddenException;
use Minhyung\Mybox\Exception\InsufficientStorageException;
use Minhyung\Mybox\Exception\InvalidArgumentException;
use Minhyung\Mybox\Exception\LockedException;
use Minhyung\Mybox\Exception\NotFoundException;
use Minhyung\Mybox\Exception\RateLimitException;
use Minhyung\Mybox\Exception\ServerException;
use Minhyung\Mybox\Exception\TransportException;
use Minhyung\Mybox\Exception\UnauthorizedException;
use Minhyung\Mybox\Exception\UnprocessableEntityException;
use Minhyung\Mybox\Http\RetryPolicy;
use Minhyung\Mybox\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class TransportTest extends TestCase
{
    /**
     * @return iterable<string, array{int, class-string<ApiException>}>
     */
    public static function errorStatuses(): iterable
    {
        yield '400' => [400, BadRequestException::class];
        yield '401' => [401, UnauthorizedException::class];
        yield '403' => [403, ForbiddenException::class];
        yield '404' => [404, NotFoundException::class];
        yield '409' => [409, ConflictException::class];
        yield '422' => [422, UnprocessableEntityException::class];
        yield '423' => [423, LockedException::class];
        yield '429' => [429, RateLimitException::class];
        yield '500' => [500, ServerException::class];
        yield '502' => [502, ServerException::class];
        yield '503' => [503, ServerException::class];
        yield '507' => [507, InsufficientStorageException::class];
    }

    /**
     * @param class-string<ApiException> $expected
     */
    #[DataProvider('errorStatuses')]
    public function testEachDocumentedStatusMapsToItsOwnException(int $status, string $expected): void
    {
        $this->willRespond(['code' => 'PLAT-' . $status, 'message' => 'ERR'], $status);

        try {
            $this->client()->drive()->storage();
            self::fail('Expected an ApiException.');
        } catch (ApiException $e) {
            self::assertInstanceOf($expected, $e);
            self::assertSame($status, $e->status);
            self::assertSame('PLAT-' . $status, $e->errorCode);
        }
    }

    public function testErrorBodyFieldsAreCarriedOntoTheException(): void
    {
        $this->willRespondWithFixture('error', 404);

        try {
            $this->client()->drive()->get('missing');
            self::fail('Expected a NotFoundException.');
        } catch (NotFoundException $e) {
            self::assertSame('PLAT-404', $e->errorCode);
            self::assertSame('NOT_FOUND', $e->errorMessage);
            self::assertSame('f47ac10b-58cc-4372-a567-0e02b2c3d479', $e->requestId);
            self::assertSame('2026-06-18T16:30:00+09:00', $e->timestamp);
            self::assertStringContainsString('PLAT-404 NOT_FOUND', $e->getMessage());
            self::assertStringContainsString('requestId: f47ac10b', $e->getMessage());
        }
    }

    public function testAnErrorWithANonJsonBodyStillRaisesTheRightException(): void
    {
        $this->willRespond('<html>gateway timeout</html>', 503);

        $this->expectException(ServerException::class);

        $this->client()->drive()->storage();
    }

    public function testRetriesTransientStatusesAndSucceedsOnALaterAttempt(): void
    {
        $this->willRespond([], 503);
        $this->willRespond([], 502);
        $this->willRespondWithFixture('storage');

        $storage = $this->client(new RetryPolicy(maxAttempts: 3, jitter: false))->drive()->storage();

        self::assertCount(3, $this->requests());
        self::assertCount(2, $this->sleeper->slept);
        self::assertSame([0.5, 1.0], $this->sleeper->slept);
        self::assertSame(120, $storage->fileCounts->total);
    }

    public function testDoesNotRetryAStatusOutsideThePolicy(): void
    {
        $this->willRespond([], 500);
        $this->willRespondWithFixture('storage');

        $this->expectException(ServerException::class);

        try {
            $this->client(new RetryPolicy(maxAttempts: 3))->drive()->storage();
        } finally {
            self::assertCount(1, $this->requests());
        }
    }

    public function testGivesUpAfterTheConfiguredNumberOfAttempts(): void
    {
        for ($i = 0; $i < 4; ++$i) {
            $this->willRespond(['code' => 'PLAT-429', 'message' => 'TOO_MANY_REQUESTS'], 429);
        }

        try {
            $this->client(new RetryPolicy(maxAttempts: 3, jitter: false))->drive()->storage();
            self::fail('Expected a RateLimitException.');
        } catch (RateLimitException $e) {
            self::assertCount(3, $this->requests());
            self::assertNull($e->retryAfter);
        }
    }

    public function testRetryAfterHeaderOverridesTheComputedBackoff(): void
    {
        $this->willRespond([], 429, ['Retry-After' => '12']);
        $this->willRespondWithFixture('storage');

        $this->client(new RetryPolicy(maxAttempts: 2, jitter: false))->drive()->storage();

        self::assertSame([12.0], $this->sleeper->slept);
    }

    public function testRetryAfterIsExposedOnTheFinalRateLimitException(): void
    {
        $this->willRespond([], 429, ['Retry-After' => '30']);

        try {
            $this->client()->drive()->storage();
            self::fail('Expected a RateLimitException.');
        } catch (RateLimitException $e) {
            self::assertSame(30, $e->retryAfter);
        }
    }

    public function testAConnectionFailureBecomesATransportException(): void
    {
        $this->http->addException(new \Http\Client\Exception\NetworkException(
            'connection reset',
            new Request('GET', 'https://open-api.mybox.naver.com/v1/drive/storage'),
        ));

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('connection reset');

        $this->client()->drive()->storage();
    }

    public function testAMalformedSuccessBodyBecomesATransportException(): void
    {
        $this->willRespond('not json at all');

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('not a JSON object');

        $this->client()->drive()->storage();
    }

    public function testEmptyBodyWhereAPayloadWasExpectedIsReported(): void
    {
        $this->willRespondEmpty(200);

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('empty body');

        $this->client()->drive()->storage();
    }

    public function testTheUserAgentIdentifiesTheSdkAndKeepsTheCallersPrefix(): void
    {
        $this->willRespondWithFixture('storage');

        $config = new ClientConfig('mbx_pat_test', userAgent: 'my-app/2.0');
        self::assertStringStartsWith('my-app/2.0 minhyung-mybox-php/', $config->resolvedUserAgent());

        $this->client()->drive()->storage();
        self::assertStringContainsString('minhyung-mybox-php/', $this->lastRequest()->getHeaderLine('User-Agent'));
    }

    public function testAnEmptyTokenIsRejectedUpFront(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('personal access token is required');

        new ClientConfig('  ');
    }

    public function testABaseUriThatIsNotAUrlIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new ClientConfig('mbx_pat_test', baseUri: 'not-a-url');
    }
}
