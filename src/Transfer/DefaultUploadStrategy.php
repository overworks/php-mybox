<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Transfer;

use Minhyung\Mybox\Model\UploadTicket;
use Minhyung\Mybox\Request\UploadRequest;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Posts the file to the storage host as `multipart/form-data`.
 *
 * The format is not in the published documentation; it was established by
 * probing the live service (see `docs/transfer-protocol.md`). Three details
 * are load-bearing:
 *
 * - The verb is POST. PUT, GET and HEAD are not routed and answer 404.
 * - The part **must** be named `Filedata`, with that exact capitalisation —
 *   the legacy Flash-uploader convention Naver's storage tier still follows.
 *   Any other name, casing included, is rejected with 400 "Param Not Exist".
 * - No `Authorization` header. The URL carries its own `stoken`, and the
 *   storage host is not the API host the personal access token belongs to.
 *
 * A successful upload answers `200 {"resourceId": …, "name": …, "fileSize": …}`.
 */
final class DefaultUploadStrategy implements UploadStrategy
{
    /** The one part name the storage host accepts. */
    public const PART_NAME = 'Filedata';

    public function __construct(private readonly string $contentType = 'application/octet-stream')
    {
    }

    public function createRequest(
        RequestFactoryInterface $requestFactory,
        UploadRequest $request,
        UploadTicket $ticket,
        StreamInterface $body,
    ): RequestInterface {
        $boundary = 'mybox' . bin2hex(random_bytes(16));
        $remaining = max(0, $request->fileSize - $ticket->offset);

        $prefix = sprintf(
            "--%s\r\nContent-Disposition: form-data; name=\"%s\"; filename=\"%s\"\r\nContent-Type: %s\r\n\r\n",
            $boundary,
            self::PART_NAME,
            self::escapeFileName($request->fileName),
            $this->contentType,
        );
        $suffix = sprintf("\r\n--%s--\r\n", $boundary);

        $envelope = new MultipartStream($prefix, $body, $suffix, $remaining);

        $psrRequest = $requestFactory->createRequest('POST', $ticket->uploadUrl)
            ->withHeader('Content-Type', 'multipart/form-data; boundary=' . $boundary)
            ->withHeader('Content-Length', (string) $envelope->getSize())
            ->withBody($envelope);

        if ($ticket->offset > 0 && $request->fileSize > 0) {
            // Note the missing `bytes ` prefix: the storage host wants the bare
            // `start-end/total` form, not the RFC 9110 spelling.
            $psrRequest = $psrRequest->withHeader(
                'Content-Range',
                sprintf('%d-%d/%d', $ticket->offset, $request->fileSize - 1, $request->fileSize),
            );
        }

        return $psrRequest;
    }

    /**
     * Keeps a quote or a newline in a file name from breaking out of the
     * Content-Disposition header.
     */
    private static function escapeFileName(string $name): string
    {
        return str_replace(['"', "\r", "\n"], ['\\"', '', ''], $name);
    }
}
