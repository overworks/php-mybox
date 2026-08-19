<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Tests\Integration;

use Minhyung\Mybox\Exception\LockedException;
use Minhyung\Mybox\Request\UploadRequest;
use PHPUnit\Framework\Attributes\Group;

/**
 * Covers resuming an upload that was genuinely cut mid-flight.
 *
 * A short body is rejected as a size mismatch, so the interruption has to be
 * real: the request declares its full length and then the socket dies. That
 * needs raw socket control, which is why this does not go through the SDK's
 * uploader for the *first* leg.
 */
#[Group('integration')]
final class ResumableUploadTest extends IntegrationTestCase
{
    private const BOUNDARY = 'myboxintegrationboundary0123';
    private const SIZE = 12 * 1024 * 1024;
    private const DELIVER = 5 * 1024 * 1024;

    public function testAnInterruptedUploadResumesAndReassemblesCorrectly(): void
    {
        $mybox = $this->mybox();

        $path = tempnam(sys_get_temp_dir(), 'mybox') ?: sys_get_temp_dir() . '/mybox-resume';
        $data = random_bytes(self::SIZE);
        file_put_contents($path, $data);

        try {
            $request = new UploadRequest(
                fileName: 'resume.bin',
                fileSize: self::SIZE,
                parentId: $this->sandboxId(),
                resume: true,
                modifiedTime: (new \DateTimeImmutable())->setTimestamp((int) filemtime($path)),
            );

            // MYBOX only ever matches the KST spelling of modifiedTime, so a
            // host in any other zone must still send that. This is the guard.
            $modifiedTime = $request->toBody()['modifiedTime'];
            self::assertIsString($modifiedTime);
            self::assertStringEndsWith('+09:00', $modifiedTime);

            $sent = $this->deliverThenCut($mybox->files()->createUploadUrl($request)->uploadUrl, $path, 'resume.bin');
            self::assertSame(self::DELIVER, $sent, 'The probe should have delivered exactly the intended prefix.');

            $held = $this->offsetOnceSettled($request);
            self::assertGreaterThan(0, $held, 'MYBOX did not retain the interrupted upload.');

            $result = $mybox->upload()->fromFile(
                $path,
                fileName: 'resume.bin',
                parentId: $this->sandboxId(),
                resume: true,
            );

            self::assertTrue($result->wasResumed(), 'The upload restarted instead of resuming.');
            self::assertLessThan(self::SIZE, $result->bytesSent, 'The whole file was re-sent.');
            self::assertSame(self::SIZE, $result->fileSize);
            self::assertSame($data, $mybox->download()->contents($result->resourceId));
        } finally {
            @unlink($path);
        }
    }

    /**
     * Reserves repeatedly until the interrupted transfer's lock clears, then
     * reports the offset MYBOX is holding.
     */
    private function offsetOnceSettled(UploadRequest $request): int
    {
        for ($i = 0; $i < 20; ++$i) {
            try {
                return $this->mybox()->files()->createUploadUrl($request)->offset;
            } catch (LockedException) {
                sleep(3);
            }
        }

        self::fail('The interrupted upload stayed locked.');
    }

    /**
     * Writes part of the multipart body over a raw TLS socket and then drops
     * the connection, leaving the declared Content-Length unfulfilled.
     *
     * @return int Bytes of file content actually delivered.
     */
    private function deliverThenCut(string $url, string $path, string $fileName): int
    {
        $parts = parse_url($url);
        $host = (string) ($parts['host'] ?? '');
        $head = sprintf(
            "--%s\r\nContent-Disposition: form-data; name=\"Filedata\"; filename=\"%s\"\r\n"
            . "Content-Type: application/octet-stream\r\n\r\n",
            self::BOUNDARY,
            $fileName,
        );
        $total = strlen($head) + self::SIZE + strlen("\r\n--" . self::BOUNDARY . "--\r\n");

        $socket = stream_socket_client(
            'ssl://' . $host . ':443',
            $errno,
            $errstr,
            30,
            STREAM_CLIENT_CONNECT,
            stream_context_create(['ssl' => ['peer_name' => $host]]),
        );

        self::assertNotFalse($socket, sprintf('Could not reach the storage host: %s (%d)', $errstr, $errno));

        fwrite($socket, sprintf(
            "POST %s?%s HTTP/1.1\r\nHost: %s\r\nContent-Type: multipart/form-data; boundary=%s\r\n"
            . "Content-Length: %d\r\nConnection: close\r\n\r\n",
            (string) ($parts['path'] ?? '/'),
            (string) ($parts['query'] ?? ''),
            $host,
            self::BOUNDARY,
            $total,
        ));
        fwrite($socket, $head);

        $handle = fopen($path, 'rb');
        self::assertNotFalse($handle);

        $sent = 0;

        while ($sent < self::DELIVER) {
            $chunk = fread($handle, min(65536, self::DELIVER - $sent));

            if ($chunk === false || $chunk === '') {
                break;
            }

            $written = fwrite($socket, $chunk);

            if ($written === false || $written === 0) {
                break;
            }

            $sent += $written;
        }

        fclose($handle);
        stream_socket_shutdown($socket, STREAM_SHUT_RDWR);
        fclose($socket);

        return $sent;
    }
}
