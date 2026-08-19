<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Request;

use Minhyung\Mybox\Exception\InvalidArgumentException;

/**
 * Body for `POST /v1/drive/files`, which reserves an upload URL.
 *
 * `fileSize` is mandatory and must be exact — MYBOX rejects or truncates
 * uploads whose declared size does not match the bytes that follow.
 */
final class UploadRequest
{
    /**
     * MYBOX matches an interrupted upload by the literal `modifiedTime` string
     * it was reserved with, and only ever recognises the KST spelling. The same
     * instant written as `+00:00` is treated as a different file, so resuming
     * from a host in any other timezone would silently restart the upload.
     */
    private const MODIFIED_TIME_TIMEZONE = 'Asia/Seoul';

    /**
     * @param string             $fileName     Include the extension; MYBOX does not infer one.
     * @param int                $fileSize     Exact byte length of the payload.
     * @param string|null        $parentId     Destination folder; the root is used when null.
     * @param bool|null          $isOverwrite  Replace an existing file of the same name.
     * @param bool               $resume       Continue an interrupted upload from
     *                                         {@see \Minhyung\Mybox\Model\UploadTicket::$offset}.
     * @param \DateTimeInterface|null $modifiedTime Required whenever `$resume` is true —
     *                                         MYBOX uses it to recognise the interrupted upload.
     *                                         Pass it in any timezone; it is sent as KST, which
     *                                         is the only spelling MYBOX will match.
     */
    public function __construct(
        public readonly string $fileName,
        public readonly int $fileSize,
        public readonly ?string $parentId = null,
        public readonly ?bool $isOverwrite = null,
        public readonly bool $resume = false,
        public readonly ?\DateTimeInterface $modifiedTime = null,
    ) {
        if (trim($fileName) === '') {
            throw new InvalidArgumentException('fileName cannot be empty.');
        }

        if ($fileSize < 0) {
            throw new InvalidArgumentException(sprintf('fileSize must be 0 or greater, got %d.', $fileSize));
        }

        if ($resume && $modifiedTime === null) {
            throw new InvalidArgumentException('modifiedTime is required when resume is true.');
        }
    }

    /** @return array<string, mixed> */
    public function toBody(): array
    {
        $body = [
            'fileName' => $this->fileName,
            'fileSize' => $this->fileSize,
        ];

        if ($this->parentId !== null) {
            $body['parentId'] = $this->parentId;
        }

        if ($this->isOverwrite !== null) {
            $body['isOverwrite'] = $this->isOverwrite;
        }

        if ($this->resume) {
            $body['resume'] = true;
        }

        if ($this->modifiedTime !== null) {
            $body['modifiedTime'] = \DateTimeImmutable::createFromInterface($this->modifiedTime)
                ->setTimezone(new \DateTimeZone(self::MODIFIED_TIME_TIMEZONE))
                ->format(\DateTimeInterface::ATOM);
        }

        return $body;
    }
}
