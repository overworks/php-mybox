<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Tests\Unit;

use GuzzleHttp\Psr7\Utils;
use Minhyung\Mybox\Exception\ApiException;
use Minhyung\Mybox\Exception\InvalidArgumentException;
use Minhyung\Mybox\Exception\ServerException;
use Minhyung\Mybox\Http\RetryPolicy;
use Minhyung\Mybox\Tests\NoRewindStream;
use Minhyung\Mybox\Tests\TestCase;

final class TransferTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();

        $dir = sys_get_temp_dir() . '/mybox-sdk-test-' . bin2hex(random_bytes(6));
        mkdir($dir);
        $this->tmpDir = $dir;
    }

    /**
     * The storage host's success response, as observed live:
     * `200 {"resourceId": …, "name": …, "fileSize": …}`.
     */
    private function willAcceptUpload(string $name = 'report.pdf', int $size = 11): void
    {
        $this->willRespond(['resourceId' => 'stored-1', 'name' => $name, 'fileSize' => $size]);
    }

    /**
     * The bytes carried inside the single multipart part of a sent request.
     */
    private function uploadedPayload(int $index): string
    {
        $body = $this->sentBody($index);
        $start = strpos($body, "\r\n\r\n");

        self::assertNotFalse($start, 'The upload body is not a multipart envelope.');

        $end = strrpos($body, "\r\n--");

        self::assertNotFalse($end, 'The upload body has no closing boundary.');

        return substr($body, $start + 4, $end - $start - 4);
    }

    /**
     * Asserts the envelope names its part the way the storage host demands.
     */
    private function assertMultipartPart(int $index, string $fileName): void
    {
        $body = $this->sentBody($index);

        self::assertStringContainsString('name="Filedata"', $body, 'The part name must be exactly "Filedata".');
        self::assertStringContainsString(sprintf('filename="%s"', $fileName), $body);
        self::assertStringContainsString('Content-Type: application/octet-stream', $body);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tmpDir . '/*') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->tmpDir);

        parent::tearDown();
    }

    public function testUploadReservesAUrlThenPushesTheBytesToTheStorageHost(): void
    {
        $path = $this->tmpDir . '/report.pdf';
        file_put_contents($path, 'hello mybox');

        $this->willRespondWithFixture('upload_ticket', 201);
        $this->willAcceptUpload();

        $result = $this->client()->upload()->fromFile($path, parentId: 'folder-1', isOverwrite: true);

        [$reserve, $push] = $this->requests();

        self::assertSame('POST', $reserve->getMethod());
        self::assertSame('/v1/drive/files', $reserve->getUri()->getPath());
        self::assertSame([
            'fileName' => 'report.pdf',
            'fileSize' => 11,
            'parentId' => 'folder-1',
            'isOverwrite' => true,
        ], $this->sentJsonBody(0));

        self::assertSame('POST', $push->getMethod());
        self::assertSame('storage.example.com', $push->getUri()->getHost());
        self::assertStringStartsWith('multipart/form-data; boundary=', $push->getHeaderLine('Content-Type'));
        self::assertSame((string) strlen($this->sentBody(1)), $push->getHeaderLine('Content-Length'));
        $this->assertMultipartPart(1, 'report.pdf');
        self::assertSame('hello mybox', $this->uploadedPayload(1));
        self::assertSame(11, $result->bytesSent);
        self::assertSame(200, $result->status);
        self::assertSame('stored-1', $result->resourceId);
    }

    public function testTheStorageRequestDoesNotCarryThePersonalAccessToken(): void
    {
        $path = $this->tmpDir . '/a.txt';
        file_put_contents($path, 'x');

        $this->willRespondWithFixture('upload_ticket', 201);
        $this->willAcceptUpload();

        $this->client()->upload()->fromFile($path);

        self::assertFalse(
            $this->lastRequest()->hasHeader('Authorization'),
            'The token must not be forwarded to the storage host.',
        );
    }

    public function testResumingSeeksPastTheBytesMyboxAlreadyHasAndDeclaresTheRange(): void
    {
        $path = $this->tmpDir . '/big.bin';
        file_put_contents($path, '0123456789');

        $this->willRespond(['offset' => 4, 'uploadUrl' => 'https://storage.example.com/upload?stoken=z'], 201);
        $this->willAcceptUpload('big.bin', 10);

        $result = $this->client()->upload()->fromFile($path, resume: true);

        [$reserve, $push] = $this->requests();

        $body = $this->sentJsonBody(0);
        self::assertTrue($body['resume']);
        self::assertArrayHasKey('modifiedTime', $body);

        self::assertSame('456789', $this->uploadedPayload(1));
        self::assertSame((string) strlen($this->sentBody(1)), $push->getHeaderLine('Content-Length'));
        self::assertSame('4-9/10', $push->getHeaderLine('Content-Range'), 'The storage host wants no "bytes " prefix.');
        self::assertSame(6, $result->bytesSent);
        self::assertTrue($result->wasResumed());
    }

    public function testARetriedUploadResendsFromTheResumeOffsetNotFromByteZero(): void
    {
        $path = $this->tmpDir . '/big.bin';
        file_put_contents($path, '0123456789');

        $this->willRespond(['offset' => 4, 'uploadUrl' => 'https://storage.example.com/upload?stoken=z'], 201);
        $this->willRespondEmpty(503);
        $this->willAcceptUpload('big.bin', 10);

        $this->client(new RetryPolicy(maxAttempts: 2, jitter: false))->upload()->fromFile($path, resume: true);

        self::assertSame('456789', $this->uploadedPayload(1), 'First attempt should start at the resume offset.');
        self::assertSame('456789', $this->uploadedPayload(2), 'Retry must not rewind past the resume offset.');
    }

    public function testAnUploadIsNotReplayedWhenTheBodyCannotBeRepositioned(): void
    {
        $this->willRespondWithFixture('upload_ticket', 201);
        $this->willRespondEmpty(503);
        $this->willAcceptUpload('stream.bin', 16);

        $payload = 'streamed payload';
        $stream = new NoRewindStream(Utils::streamFor($payload));

        try {
            $this->client(new RetryPolicy(maxAttempts: 3, jitter: false))
                ->upload()
                ->fromStream($stream, 'stream.bin', strlen($payload));
            self::fail('Expected the 503 to surface rather than be retried.');
        } catch (ServerException $e) {
            self::assertSame(503, $e->status);
            self::assertCount(2, $this->requests(), 'A non-replayable body must not be retried.');
        }
    }

    public function testUploadFromStringDeclaresTheByteLengthNotTheCharacterCount(): void
    {
        $this->willRespondWithFixture('upload_ticket', 201);
        $this->willAcceptUpload('메모.txt', 6);

        $this->client()->upload()->fromString('한글', '메모.txt');

        $body = $this->sentJsonBody(0);
        self::assertSame(6, $body['fileSize']);
        self::assertSame('메모.txt', $body['fileName']);
    }

    public function testAFailedStorageResponseSurfacesAsAnApiException(): void
    {
        $this->willRespondWithFixture('upload_ticket', 201);
        $this->willRespond(['code' => 'PLAT-507', 'message' => 'INSUFFICIENT_STORAGE'], 507);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('PLAT-507');

        $this->client()->upload()->fromString('x', 'a.txt');
    }

    public function testUploadingAMissingFileFailsBeforeAnyRequest(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not a readable file');

        try {
            $this->client()->upload()->fromFile($this->tmpDir . '/nope.txt');
        } finally {
            self::assertSame([], $this->requests());
        }
    }

    public function testDownloadStreamsTheStorageResponseToDisk(): void
    {
        $this->willRespondWithFixture('download_ticket');
        $this->willRespond('file contents here');

        $target = $this->tmpDir . '/out.bin';
        $written = $this->client()->download()->toFile('file-1', $target);

        [$issue, $fetch] = $this->requests();

        self::assertSame('/v1/drive/files/file-1/download', $issue->getUri()->getPath());
        self::assertSame('GET', $fetch->getMethod());
        self::assertSame('storage.example.com', $fetch->getUri()->getHost());
        self::assertFalse($fetch->hasHeader('Authorization'));
        self::assertSame(18, $written);
        self::assertSame('file contents here', file_get_contents($target));
    }

    public function testDownloadContentsReturnsTheBodyDirectly(): void
    {
        $this->willRespondWithFixture('download_ticket');
        $this->willRespond('inline');

        self::assertSame('inline', $this->client()->download()->contents('file-1'));
    }

    public function testAFailedDownloadSurfacesAsAnApiException(): void
    {
        $this->willRespondWithFixture('download_ticket');
        $this->willRespond(['code' => 'PLAT-403', 'message' => 'FORBIDDEN'], 403);

        $this->expectException(ApiException::class);

        $this->client()->download()->contents('file-1');
    }
}
