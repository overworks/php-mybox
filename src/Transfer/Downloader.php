<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Transfer;

use Minhyung\Mybox\Api\FileApi;
use Minhyung\Mybox\Exception\ApiException;
use Minhyung\Mybox\Exception\InvalidArgumentException;
use Minhyung\Mybox\Exception\TransportException;
use Minhyung\Mybox\Http\Transport;
use Minhyung\Mybox\Model\DownloadTicket;
use Minhyung\Mybox\Support\Json;
use Psr\Http\Message\StreamInterface;

/**
 * Two-step file download: obtain a single-use URL through the API, then fetch
 * it from the storage host.
 *
 * The URL is valid for one download within ten minutes and authenticates
 * itself, so each of these methods issues a fresh one.
 */
final class Downloader
{
    public function __construct(
        private readonly Transport $transport,
        private readonly FileApi $files,
    ) {
    }

    /**
     * Downloads a file to a local path.
     *
     * The bytes are streamed in chunks, so file size is bounded by disk rather
     * than by memory.
     *
     * @return int Number of bytes written.
     */
    public function toFile(string $fileId, string $localPath): int
    {
        $handle = fopen($localPath, 'wb');

        if ($handle === false) {
            throw new InvalidArgumentException(sprintf('Could not open "%s" for writing.', $localPath));
        }

        try {
            return $this->toStream($fileId, $handle);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
        }
    }

    /**
     * Streams a file into an open resource.
     *
     * @param  resource $target
     * @return int      Number of bytes written.
     */
    public function toStream(string $fileId, mixed $target): int
    {
        if (!is_resource($target)) {
            throw new InvalidArgumentException('Expected a writable stream resource.');
        }

        $body = $this->open($fileId);
        $written = 0;

        while (!$body->eof()) {
            $chunk = $body->read(1024 * 256);

            if ($chunk === '') {
                break;
            }

            $bytes = fwrite($target, $chunk);

            if ($bytes === false) {
                throw new TransportException('Writing the downloaded file to the target stream failed.');
            }

            $written += $bytes;
        }

        return $written;
    }

    /**
     * Downloads a file into memory. Only suitable for small files.
     */
    public function contents(string $fileId): string
    {
        return (string) $this->open($fileId);
    }

    /**
     * Opens the file as a readable stream without buffering it.
     */
    public function open(string $fileId): StreamInterface
    {
        return $this->fetch($this->files->createDownloadUrl($fileId));
    }

    /**
     * Fetches a URL obtained earlier, for callers driving the two steps
     * themselves.
     */
    public function fetch(DownloadTicket $ticket): StreamInterface
    {
        // No Authorization header: the URL carries its own short-lived token,
        // and the storage host is not the API host the PAT belongs to.
        $request = $this->transport->requestFactory()->createRequest('GET', $ticket->downloadUrl);
        $response = $this->transport->send($request);
        $status = $response->getStatusCode();

        if ($status >= 400) {
            $raw = (string) $response->getBody();

            throw ApiException::fromResponse($status, Json::decodeObject($raw) ?? [], $raw);
        }

        return $response->getBody();
    }
}
