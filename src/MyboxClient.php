<?php

declare(strict_types=1);

namespace Minhyung\Mybox;

use Minhyung\Mybox\Api\DriveApi;
use Minhyung\Mybox\Api\FileApi;
use Minhyung\Mybox\Api\SearchApi;
use Minhyung\Mybox\Api\TrashApi;
use Minhyung\Mybox\Http\Sleeper;
use Minhyung\Mybox\Http\Transport;
use Minhyung\Mybox\Path\PathResolver;
use Minhyung\Mybox\Transfer\DefaultUploadStrategy;
use Minhyung\Mybox\Transfer\Downloader;
use Minhyung\Mybox\Transfer\Uploader;
use Minhyung\Mybox\Transfer\UploadStrategy;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Entry point to the Naver MYBOX Open API.
 *
 * ```php
 * $mybox = MyboxClient::create($personalAccessToken);
 *
 * $mybox->drive()->storage();
 * $mybox->files()->createFolder('업무자료');
 * $mybox->upload()->fromFile('/tmp/report.pdf');
 * ```
 *
 * Endpoints are grouped the way the documentation groups them: {@see drive()}
 * for reads and the favourite flag, {@see files()} for mutations and transfer
 * URLs, {@see search()} for the search index, {@see trash()} for the trash.
 * {@see upload()}, {@see download()} and {@see paths()} sit on top of those
 * and handle the multi-step operations.
 */
final class MyboxClient
{
    public const VERSION = '0.1.1';

    private readonly Transport $transport;
    private readonly DriveApi $drive;
    private readonly FileApi $files;
    private readonly SearchApi $search;
    private readonly TrashApi $trash;
    private readonly Uploader $uploader;
    private readonly Downloader $downloader;
    private readonly PathResolver $paths;

    /**
     * Any HTTP argument left null is resolved through php-http/discovery.
     */
    public function __construct(
        ClientConfig $config,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
        ?UploadStrategy $uploadStrategy = null,
        ?Sleeper $sleeper = null,
    ) {
        $this->transport = new Transport($config, $httpClient, $requestFactory, $streamFactory, $sleeper);
        $this->drive = new DriveApi($this->transport);
        $this->files = new FileApi($this->transport);
        $this->search = new SearchApi($this->transport);
        $this->trash = new TrashApi($this->transport);
        $this->uploader = new Uploader(
            $this->transport,
            $this->files,
            $uploadStrategy ?? new DefaultUploadStrategy(),
        );
        $this->downloader = new Downloader($this->transport, $this->files);
        $this->paths = new PathResolver($this->drive, $this->search);
    }

    /**
     * Shorthand for the common case of "just give me a client for this token".
     */
    public static function create(string $personalAccessToken): self
    {
        return new self(new ClientConfig($personalAccessToken));
    }

    public function config(): ClientConfig
    {
        return $this->transport->config();
    }

    /**
     * Storage info, listings, resource properties, favourites.
     */
    public function drive(): DriveApi
    {
        return $this->drive;
    }

    /**
     * Folder creation, transfer URLs, copy, move, rename, delete.
     */
    public function files(): FileApi
    {
        return $this->files;
    }

    /**
     * File and folder search.
     */
    public function search(): SearchApi
    {
        return $this->search;
    }

    /**
     * Trash listing, restore, and permanent deletion.
     */
    public function trash(): TrashApi
    {
        return $this->trash;
    }

    /**
     * Uploads that reserve a URL and push the bytes in one call.
     */
    public function upload(): Uploader
    {
        return $this->uploader;
    }

    /**
     * Downloads that issue a URL and stream the bytes in one call.
     */
    public function download(): Downloader
    {
        return $this->downloader;
    }

    /**
     * Path-to-id lookups, with per-client memoisation.
     */
    public function paths(): PathResolver
    {
        return $this->paths;
    }

    /**
     * Escape hatch for callers who need to reach an endpoint this SDK does not
     * model yet.
     */
    public function transport(): Transport
    {
        return $this->transport;
    }
}
