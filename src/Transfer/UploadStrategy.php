<?php

declare(strict_types=1);

namespace Minhyung\Mybox\Transfer;

use Minhyung\Mybox\Model\UploadTicket;
use Minhyung\Mybox\Request\UploadRequest;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Builds the request that carries a file's bytes to the storage URL handed
 * out by `POST /v1/drive/files`.
 *
 * MYBOX documents how to obtain that URL but not the wire format for pushing
 * bytes to it. {@see DefaultUploadStrategy} implements the format observed
 * against the live service; this interface exists so a change on Naver's side
 * can be absorbed by swapping one class.
 */
interface UploadStrategy
{
    /**
     * @param UploadRequest   $request The reservation this ticket came from —
     *                                 its `fileName` and `fileSize` must match
     *                                 what the storage host was promised.
     * @param StreamInterface $body    Bytes to send, already positioned at
     *                                 `$ticket->offset`.
     */
    public function createRequest(
        RequestFactoryInterface $requestFactory,
        UploadRequest $request,
        UploadTicket $ticket,
        StreamInterface $body,
    ): RequestInterface;
}
