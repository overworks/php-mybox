<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Tests;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Http\Mock\Client as MockClient;
use Minhyung\Mybox\ClientConfig;
use Minhyung\Mybox\Http\RetryPolicy;
use Minhyung\Mybox\MyboxClient;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Psr\Http\Message\RequestInterface;

/**
 * Wires a {@see MyboxClient} onto an in-memory PSR-18 client so tests can
 * queue canned responses and assert on the requests that were produced.
 */
abstract class TestCase extends BaseTestCase
{
    protected MockClient $http;
    protected CapturingClient $capturing;
    protected RecordingSleeper $sleeper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->http = new MockClient();
        $this->capturing = new CapturingClient($this->http);
        $this->sleeper = new RecordingSleeper();
    }

    protected function client(?RetryPolicy $retryPolicy = null): MyboxClient
    {
        $factory = new HttpFactory();

        return new MyboxClient(
            new ClientConfig('mbx_pat_test', retryPolicy: $retryPolicy ?? RetryPolicy::none()),
            $this->capturing,
            $factory,
            $factory,
            sleeper: $this->sleeper,
        );
    }

    /**
     * Queues a JSON response body.
     *
     * @param array<string, mixed>|string $body
     * @param array<string, string>       $headers
     */
    protected function willRespond(array|string $body = [], int $status = 200, array $headers = []): void
    {
        $payload = is_string($body) ? $body : (string) json_encode($body, JSON_UNESCAPED_UNICODE);

        $this->http->addResponse(new Response($status, $headers + ['Content-Type' => 'application/json'], $payload));
    }

    /**
     * Queues a fixture captured from the published MYBOX documentation.
     */
    protected function willRespondWithFixture(string $name, int $status = 200): void
    {
        $this->willRespond($this->fixture($name), $status);
    }

    protected function willRespondEmpty(int $status = 204): void
    {
        $this->http->addResponse(new Response($status, [], ''));
    }

    /**
     * @return array<string, mixed>
     */
    protected function fixture(string $name): array
    {
        $path = __DIR__ . '/Fixtures/' . $name . '.json';
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($decoded, sprintf('Fixture "%s" is not a JSON object.', $name));

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * The single request the client made.
     */
    protected function lastRequest(): RequestInterface
    {
        $requests = $this->http->getRequests();

        self::assertNotEmpty($requests, 'Expected the client to have made a request.');

        return $requests[count($requests) - 1];
    }

    /**
     * @return list<RequestInterface>
     */
    protected function requests(): array
    {
        return array_values($this->http->getRequests());
    }

    /**
     * Asserts the method and full request target of the last request.
     */
    protected function assertRequest(string $method, string $pathAndQuery): void
    {
        $request = $this->lastRequest();
        $uri = $request->getUri();
        $actual = $uri->getPath() . ($uri->getQuery() === '' ? '' : '?' . $uri->getQuery());

        self::assertSame($method, $request->getMethod());
        self::assertSame($pathAndQuery, urldecode($actual));
        self::assertSame('open-api.mybox.naver.com', $uri->getHost());
        self::assertSame('Bearer mbx_pat_test', $request->getHeaderLine('Authorization'));
    }

    /**
     * Body of the request at `$index`, decoded as a JSON object.
     *
     * @return array<string, mixed>
     */
    protected function sentJsonBody(int $index): array
    {
        $decoded = json_decode($this->sentBody($index), true);

        self::assertIsArray($decoded, sprintf('Request %d did not carry a JSON object body.', $index));

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * Body of the request at `$index`, as it looked when it was sent.
     */
    protected function sentBody(int $index): string
    {
        self::assertArrayHasKey($index, $this->capturing->bodies, sprintf('No request was sent at index %d.', $index));

        return $this->capturing->bodies[$index];
    }

    /**
     * Decoded JSON body of the last request.
     *
     * @return array<string, mixed>
     */
    protected function lastRequestBody(): array
    {
        $decoded = json_decode((string) $this->lastRequest()->getBody(), true);

        self::assertIsArray($decoded, 'Expected the request to carry a JSON object body.');

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
