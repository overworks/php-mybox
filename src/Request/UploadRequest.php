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
     * @param string             $fileName     Include the extension; MYBOX does not infer one.
     * @param int                $fileSize     Exact byte length of the payload.
     * @param string|null        $parentId     Destination folder; the root is used when null.
     * @param bool|null          $isOverwrite  Replace an existing file of the same name.
     * @param bool               $resume       Continue an interrupted upload from
     *                                         {@see \Minhyung\Mybox\Model\UploadTicket::$offset}.
     * @param \DateTimeInterface|null $modifiedTime Required whenever `$resume` is true —
     *                                         MYBOX uses it to recognise the interrupted upload.
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
            $body['modifiedTime'] = $this->modifiedTime->format(\DateTimeInterface::ATOM);
        }

        return $body;
    }
}
