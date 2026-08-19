<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Transfer;

use Minhyung\Mybox\Api\FileApi;
use Minhyung\Mybox\Exception\ApiException;
use Minhyung\Mybox\Exception\InvalidArgumentException;
use Minhyung\Mybox\Exception\TransportException;
use Minhyung\Mybox\Http\Transport;
use Minhyung\Mybox\Model\UploadTicket;
use Minhyung\Mybox\Request\UploadRequest;
use Minhyung\Mybox\Support\Json;
use Psr\Http\Message\StreamInterface;

/**
 * Two-step file upload: reserve a URL through the API, then push the bytes to
 * the storage host it points at.
 *
 * ```php
 * $mybox->upload()->fromFile('/tmp/report.pdf', parentId: $folderId, isOverwrite: true);
 * ```
 *
 * The second step's wire format is not published by Naver; see
 * {@see DefaultUploadStrategy} and `docs/transfer-protocol.md` for what it is
 * and how it was established. Bytes are streamed, so file size is bounded by
 * disk rather than by memory.
 */
final class Uploader
{
    public function __construct(
        private readonly Transport $transport,
        private readonly FileApi $files,
        private readonly UploadStrategy $strategy = new DefaultUploadStrategy(),
    ) {
    }

    /**
     * Uploads a local file.
     *
     * @param string      $localPath Path to read from.
     * @param string|null $fileName  Name to store it under; the basename of `$localPath` by default.
     * @param string|null $parentId  Destination folder; the root when null.
     * @param bool        $resume    Ask MYBOX whether part of this file is already stored and
     *                               continue from there. Requires the file's modification time,
     *                               which is read from `$localPath`. Note that no interrupted
     *                               upload has yet been observed to leave a non-zero offset —
     *                               see `docs/transfer-protocol.md`.
     */
    public function fromFile(
        string $localPath,
        ?string $fileName = null,
        ?string $parentId = null,
        ?bool $isOverwrite = null,
        bool $resume = false,
    ): UploadResult {
        if (!is_file($localPath) || !is_readable($localPath)) {
            throw new InvalidArgumentException(sprintf('"%s" is not a readable file.', $localPath));
        }

        $size = filesize($localPath);

        if ($size === false) {
            throw new InvalidArgumentException(sprintf('Could not determine the size of "%s".', $localPath));
        }

        $modifiedTime = null;

        if ($resume) {
            $mtime = filemtime($localPath);
            $modifiedTime = $mtime === false ? null : (new \DateTimeImmutable())->setTimestamp($mtime);

            if ($modifiedTime === null) {
                throw new InvalidArgumentException(
                    sprintf('Could not read the modification time of "%s", which resuming requires.', $localPath),
                );
            }
        }

        $request = new UploadRequest(
            fileName: $fileName ?? basename($localPath),
            fileSize: $size,
            parentId: $parentId,
            isOverwrite: $isOverwrite,
            resume: $resume,
            modifiedTime: $modifiedTime,
        );
        $ticket = $this->files->createUploadUrl($request);

        $handle = fopen($localPath, 'rb');

        if ($handle === false) {
            throw new TransportException(sprintf('Could not open "%s" for reading.', $localPath));
        }

        if ($ticket->offset > 0 && fseek($handle, $ticket->offset) !== 0) {
            fclose($handle);

            throw new TransportException(sprintf(
                'Could not seek to byte %d of "%s" to resume the upload.',
                $ticket->offset,
                $localPath,
            ));
        }

        // The stream takes ownership of the handle from here on, so closing it
        // closes the underlying file too.
        $body = $this->transport->streamFactory()->createStreamFromResource($handle);

        try {
            return $this->sendTicket($request, $ticket, $body);
        } finally {
            $body->close();
        }
    }

    /**
     * Uploads from an already-open stream.
     *
     * The size must be exact: MYBOX reserves the upload against the declared
     * length. Resuming is not offered here because the caller's stream may not
     * be seekable — use {@see self::fromFile()} for that.
     *
     * @param resource|StreamInterface $stream
     */
    public function fromStream(
        mixed $stream,
        string $fileName,
        int $size,
        ?string $parentId = null,
        ?bool $isOverwrite = null,
    ): UploadResult {
        $body = $stream instanceof StreamInterface
            ? $stream
            : $this->transport->streamFactory()->createStreamFromResource($this->assertResource($stream));

        $request = new UploadRequest(
            fileName: $fileName,
            fileSize: $size,
            parentId: $parentId,
            isOverwrite: $isOverwrite,
        );

        return $this->sendTicket($request, $this->files->createUploadUrl($request), $body);
    }

    /**
     * Uploads a string as a file. Convenient for generated content.
     */
    public function fromString(
        string $contents,
        string $fileName,
        ?string $parentId = null,
        ?bool $isOverwrite = null,
    ): UploadResult {
        $request = new UploadRequest(
            fileName: $fileName,
            fileSize: strlen($contents),
            parentId: $parentId,
            isOverwrite: $isOverwrite,
        );

        return $this->sendTicket(
            $request,
            $this->files->createUploadUrl($request),
            $this->transport->streamFactory()->createStream($contents),
        );
    }

    /**
     * Pushes bytes to a URL obtained earlier, for callers driving the two
     * steps themselves.
     *
     * @param UploadRequest $request The reservation the ticket came from.
     */
    public function sendTicket(UploadRequest $request, UploadTicket $ticket, StreamInterface $body): UploadResult
    {
        $httpRequest = $this->strategy->createRequest(
            $this->transport->requestFactory(),
            $request,
            $ticket,
            $body,
        );
        $response = $this->transport->send($httpRequest);
        $status = $response->getStatusCode();
        $raw = (string) $response->getBody();
        $decoded = Json::decodeObject($raw);

        if ($status >= 400) {
            throw ApiException::fromResponse($status, $decoded ?? [], $raw);
        }

        $resourceId = is_string($decoded['resourceId'] ?? null) ? $decoded['resourceId'] : null;

        if ($resourceId === null) {
            throw new TransportException(sprintf(
                'The storage host accepted the upload (HTTP %d) but did not name the stored file: %s',
                $status,
                $raw === '' ? '(empty body)' : $raw,
            ));
        }

        return new UploadResult(
            resourceId: $resourceId,
            name: is_string($decoded['name'] ?? null) ? $decoded['name'] : $request->fileName,
            fileSize: is_int($decoded['fileSize'] ?? null) ? $decoded['fileSize'] : $request->fileSize,
            ticket: $ticket,
            status: $status,
            rawBody: $raw,
            decodedBody: $decoded,
            bytesSent: max(0, $request->fileSize - $ticket->offset),
        );
    }

    /**
     * @return resource
     */
    private function assertResource(mixed $stream): mixed
    {
        if (!is_resource($stream)) {
            throw new InvalidArgumentException('Expected a stream resource or a PSR-7 StreamInterface.');
        }

        return $stream;
    }
}
