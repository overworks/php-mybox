<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Transfer;

use Minhyung\Mybox\Exception\TransportException;
use Psr\Http\Message\StreamInterface;

/**
 * Concatenates a prefix, a payload stream, and a suffix without buffering.
 *
 * The storage host wants `multipart/form-data`, but wrapping a file in a
 * multipart envelope must not mean reading it into memory first — a 10 GB
 * upload has to stay a 10 GB upload. This presents the envelope as one stream
 * and reads the payload through, so memory stays flat regardless of file size.
 *
 * @internal
 */
final class MultipartStream implements StreamInterface
{
    private int $position = 0;
    private readonly int $prefixLength;
    private readonly int $suffixLength;

    /**
     * Where the payload stream started. A resumed upload hands us a file
     * already seeked past the bytes MYBOX holds, so rewinding must return
     * there rather than to byte zero of the file.
     */
    private readonly int $payloadStart;

    public function __construct(
        private readonly string $prefix,
        private readonly StreamInterface $payload,
        private readonly string $suffix,
        private readonly int $payloadLength,
    ) {
        $this->prefixLength = strlen($prefix);
        $this->suffixLength = strlen($suffix);
        $this->payloadStart = $payload->isSeekable() ? $payload->tell() : 0;
    }

    public function getSize(): int
    {
        return $this->prefixLength + $this->payloadLength + $this->suffixLength;
    }

    public function read(int $length): string
    {
        if ($length <= 0) {
            return '';
        }

        $chunk = '';

        // Prefix.
        if ($this->position < $this->prefixLength) {
            $chunk = substr($this->prefix, $this->position, $length);
            $this->position += strlen($chunk);
            $length -= strlen($chunk);

            if ($length === 0) {
                return $chunk;
            }
        }

        // Payload.
        $payloadEnd = $this->prefixLength + $this->payloadLength;

        if ($this->position < $payloadEnd) {
            $wanted = min($length, $payloadEnd - $this->position);
            $read = $this->payload->read($wanted);

            if ($read === '') {
                // The payload ended sooner than its declared length; treating
                // this as success would silently upload a truncated file.
                throw new TransportException(sprintf(
                    'The upload payload ended after %d bytes but %d were declared.',
                    $this->position - $this->prefixLength,
                    $this->payloadLength,
                ));
            }

            $chunk .= $read;
            $this->position += strlen($read);
            $length -= strlen($read);

            if ($length === 0) {
                return $chunk;
            }
        }

        // Suffix.
        $offset = $this->position - $payloadEnd;

        if ($offset < $this->suffixLength) {
            $tail = substr($this->suffix, $offset, $length);
            $chunk .= $tail;
            $this->position += strlen($tail);
        }

        return $chunk;
    }

    public function eof(): bool
    {
        return $this->position >= $this->getSize();
    }

    public function tell(): int
    {
        return $this->position;
    }

    public function isSeekable(): bool
    {
        return $this->payload->isSeekable();
    }

    /**
     * Only a full rewind is supported — enough for the retry path, which is
     * the sole reason this stream ever needs to move backwards.
     */
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if ($whence !== SEEK_SET || $offset !== 0) {
            throw new TransportException('A multipart upload body can only be rewound to the start.');
        }

        $this->rewind();
    }

    public function rewind(): void
    {
        if (!$this->payload->isSeekable()) {
            throw new TransportException('This upload body cannot be rewound.');
        }

        $this->payload->seek($this->payloadStart);
        $this->position = 0;
    }

    public function __toString(): string
    {
        return $this->getContents();
    }

    public function getContents(): string
    {
        $contents = '';

        while (!$this->eof()) {
            $contents .= $this->read(1024 * 256);
        }

        return $contents;
    }

    public function close(): void
    {
        $this->payload->close();
    }

    public function detach()
    {
        return $this->payload->detach();
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function write(string $string): int
    {
        throw new TransportException('An upload body is not writable.');
    }

    public function getMetadata(?string $key = null): mixed
    {
        return $key === null ? [] : null;
    }
}
