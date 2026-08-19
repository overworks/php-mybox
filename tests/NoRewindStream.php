<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Tests;

use Psr\Http\Message\StreamInterface;

/**
 * A stream that reports itself as non-seekable, standing in for a pipe, a
 * socket, or any other source that cannot be replayed.
 */
final class NoRewindStream implements StreamInterface
{
    public function __construct(private readonly StreamInterface $inner)
    {
    }

    public function isSeekable(): bool
    {
        return false;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        throw new \RuntimeException('This stream is not seekable.');
    }

    public function rewind(): void
    {
        throw new \RuntimeException('This stream is not seekable.');
    }

    public function __toString(): string
    {
        return $this->inner->__toString();
    }

    public function close(): void
    {
        $this->inner->close();
    }

    public function detach()
    {
        return $this->inner->detach();
    }

    public function getSize(): ?int
    {
        return $this->inner->getSize();
    }

    public function tell(): int
    {
        return $this->inner->tell();
    }

    public function eof(): bool
    {
        return $this->inner->eof();
    }

    public function isWritable(): bool
    {
        return $this->inner->isWritable();
    }

    public function write(string $string): int
    {
        return $this->inner->write($string);
    }

    public function isReadable(): bool
    {
        return $this->inner->isReadable();
    }

    public function read(int $length): string
    {
        return $this->inner->read($length);
    }

    public function getContents(): string
    {
        return $this->inner->getContents();
    }

    public function getMetadata(?string $key = null)
    {
        return $this->inner->getMetadata($key);
    }
}
