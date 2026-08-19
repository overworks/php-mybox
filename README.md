# php-mybox

A framework-agnostic PHP SDK for the [Naver MYBOX Open API](https://developers.mybox.naver.com/).

한국어 문서는 [README.ko.md](README.ko.md)를 참고하세요.

- Covers all 20 published endpoints, one typed method each
- PSR-18 / PSR-17 based — brings no HTTP client of its own
- Readonly models and backed enums instead of associative arrays
- Transparent cursor pagination, resumable uploads, streamed downloads
- Automatic backoff on the statuses that deserve it, and a typed exception per error code

## Requirements

PHP 8.2+ and any PSR-18 HTTP client. If your project does not already have one:

```bash
composer require guzzlehttp/guzzle
```

## Installation

```bash
composer require minhyung/mybox
```

## Getting a personal access token

1. Sign in to MYBOX on the web with your Naver account.
2. Go to **설정 → 계정 및 개인 액세스 토큰 관리** and click **토큰 생성**.
3. Give the token a name and an expiry of 30, 60, 90, or 180 days.
4. Copy it immediately — it is shown exactly once.

An account can hold at most five tokens. Treat one like a password: anybody
holding it has full access to your drive.

## Quick start

```php
use Minhyung\Mybox\MyboxClient;

$mybox = MyboxClient::create($_ENV['MYBOX_PAT']);

// How much room is left?
$storage = $mybox->drive()->storage();
printf("%.1f GB of %.1f GB used\n", $storage->usedBytes / 1e9, $storage->quotaBytes / 1e9);

// Create a folder and put a file in it
$folder = $mybox->files()->createFolder('업무자료');
$mybox->upload()->fromFile('/tmp/report.pdf', parentId: $folder->resourceId);

// Walk the whole folder, one page at a time, without holding it all in memory
foreach ($mybox->drive()->listFolderAll($folder->resourceId) as $item) {
    printf("%-40s %10d bytes\n", $item->name, $item->size);
}
```

## Endpoints

Everything is grouped the way the official documentation groups it.

### `drive()` — reading and favourites

| Method | Endpoint |
|---|---|
| `storage()` | `GET /v1/drive/storage` |
| `setTrashAutoDeleteDays(int $days)` | `PATCH /v1/drive/storage` |
| `listRoot(?ListOptions)` | `GET /v1/drive/resources` |
| `listFolder(string $folderId, ?ListOptions)` | `GET /v1/drive/folders/{folderId}/resources` |
| `get(string $resourceId)` | `GET /v1/drive/resources/{resourceId}` |
| `favorite(string $resourceId)` | `POST /v1/drive/resources/{resourceId}/favorite` |
| `unfavorite(string $resourceId)` | `POST /v1/drive/resources/{resourceId}/unfavorite` |

`listRootAll()` and `listFolderAll()` return a paginator over the same data.

### `files()` — mutations and transfer URLs

| Method | Endpoint |
|---|---|
| `createFolder(string $name, ?string $parentId)` | `POST /v1/drive/folders` |
| `createUploadUrl(UploadRequest)` | `POST /v1/drive/files` |
| `createDownloadUrl(string $fileId)` | `GET /v1/drive/files/{fileId}/download` |
| `copy(string $resourceId, ?CopyOptions)` | `POST /v1/drive/resources/{resourceId}/copy` |
| `delete(string $resourceId)` | `DELETE /v1/drive/resources/{resourceId}` |
| `move(string $resourceId, string $parentId, bool $isOverwrite)` | `POST /v1/drive/resources/{resourceId}/move` |
| `rename(string $resourceId, string $name)` | `POST /v1/drive/resources/{resourceId}/rename` |

### `search()` — the search index

| Method | Endpoint |
|---|---|
| `files(SearchFilesOptions)` | `GET /v1/search/resources/files` |
| `folders(SearchFoldersOptions)` | `GET /v1/search/resources/folders` |

`filesAll()` and `foldersAll()` page through the results.

### `trash()`

| Method | Endpoint |
|---|---|
| `list(?TrashListOptions)` | `GET /v1/drive/trash` |
| `restore(string $resourceId, bool $isOverwrite)` | `POST /v1/drive/trash/{resourceId}/restore` |
| `purge(string $resourceId)` | `DELETE /v1/drive/trash/{resourceId}` |
| `empty()` | `DELETE /v1/drive/trash` |

`listAll()` pages through the trash.

## Listing and sorting

```php
use Minhyung\Mybox\Model\Enum\SortField;
use Minhyung\Mybox\Model\Enum\SortOrder;
use Minhyung\Mybox\Request\ListOptions;

$page = $mybox->drive()->listRoot(new ListOptions(
    sortBy: SortField::ModifiedAt,
    sortOrder: SortOrder::Desc,
    count: 500,          // 1–1000, MYBOX defaults to 100
));

$page->fileCount;        // files directly in this folder
$page->subFolderCount;   // folders directly in this folder
$page->nextCursor();     // null on the last page
```

MYBOX always lists folders before files, whatever you sort by.

### Pagination

```php
$all   = $mybox->drive()->listRootAll();

foreach ($all as $item) { /* one item at a time, pages fetched lazily */ }
foreach ($all->pages() as $page) { /* per-page metadata */ }

$all->take(50);   // stops requesting as soon as it has 50
$all->all();      // everything, eagerly
```

## Uploading

```php
// From a local file — reserves the URL and pushes the bytes in one call
$mybox->upload()->fromFile('/tmp/report.pdf', parentId: $folderId, isOverwrite: true);

// From memory
$mybox->upload()->fromString($csv, 'export.csv', parentId: $folderId);

// From an open stream, when you know the exact length
$mybox->upload()->fromStream($handle, 'video.mp4', $size, parentId: $folderId);

// Ask MYBOX whether it kept part of an interrupted upload, and continue from there
$mybox->upload()->fromFile('/tmp/big.zip', resume: true);
```

Each returns an `UploadResult` carrying the stored file's `resourceId`, `name`
and `fileSize`. Uploads stream — a 10 GB file uses no more memory than a 10 KB
one.

The declared size must match the payload exactly; MYBOX reserves the upload
against it and answers 500 on a mismatch. `maxFileBytes` from `storage()` is
the per-file ceiling.

Sending the bytes is a second call to a separate storage host, and its wire
format is not part of the published documentation. It was established against
the live service and is documented in
[docs/transfer-protocol.md](docs/transfer-protocol.md); `resume` is the one
part that could not be confirmed, since no interrupted upload was ever observed
to leave a non-zero offset behind.

## Downloading

```php
$mybox->download()->toFile($fileId, '/tmp/report.pdf');   // streamed to disk
$mybox->download()->contents($fileId);                    // into memory
$stream = $mybox->download()->open($fileId);              // PSR-7 stream
```

Each call issues a fresh URL. MYBOX documents these as single-use and valid for
ten minutes. Downloads stream too, so `toFile()` is bounded by disk rather than
by memory.

## Searching

```php
use Minhyung\Mybox\Model\Enum\Category;
use Minhyung\Mybox\Request\SearchFilesOptions;

foreach ($mybox->search()->filesAll(new SearchFilesOptions(
    q: '1월 회의록 pdf',
    category: Category::Document,
    parentPath: '/문서/',
)) as $file) {
    echo $file->path, PHP_EOL;
}
```

A search needs at least one of `q`, `category`, or a date bound — the SDK
rejects an empty query locally rather than spending one of your quota slots on
it. Page size is 20–200 here, not 1–1000.

## Paths

MYBOX addresses everything by id. To start from a path instead:

```php
$folderId = $mybox->paths()->folderId('/문서/2026');
$fileId   = $mybox->paths()->fileId('/문서/2026/회의록.pdf');
```

A folder normally resolves in a single search call; when the index has not
caught up the resolver walks down from the root instead. Results are memoised
per client — call `clearCache()` after renaming or moving something.

## Error handling

Every failure is a `MyboxException`. The API's own errors are `ApiException`
subclasses carrying the `requestId` you would quote to support:

```php
use Minhyung\Mybox\Exception\ApiException;
use Minhyung\Mybox\Exception\InsufficientStorageException;
use Minhyung\Mybox\Exception\NotFoundException;
use Minhyung\Mybox\Exception\RateLimitException;

try {
    $mybox->drive()->get($resourceId);
} catch (NotFoundException) {
    // 404
} catch (RateLimitException $e) {
    sleep($e->retryAfter ?? 60);
} catch (InsufficientStorageException) {
    // 507 — the account is full
} catch (ApiException $e) {
    error_log("{$e->errorCode} {$e->errorMessage} (requestId {$e->requestId})");
}
```

| HTTP | Exception |
|---|---|
| 400 | `BadRequestException` |
| 401 | `UnauthorizedException` |
| 403 | `ForbiddenException` |
| 404 | `NotFoundException` |
| 409 | `ConflictException` |
| 422 | `UnprocessableEntityException` |
| 423 | `LockedException` |
| 429 | `RateLimitException` |
| 500, 502, 503 | `ServerException` |
| 507 | `InsufficientStorageException` |

Connection failures and unparseable responses raise `TransportException`;
arguments rejected before any request raise `InvalidArgumentException`.

## Retries

429, 502, and 503 are retried three times with exponential backoff and full
jitter, honouring `Retry-After` when the server sends one. 500 is not retried —
MYBOX returns it for genuine faults rather than congestion.

```php
use Minhyung\Mybox\ClientConfig;
use Minhyung\Mybox\Http\RetryPolicy;
use Minhyung\Mybox\MyboxClient;

$mybox = new MyboxClient(new ClientConfig($token, retryPolicy: new RetryPolicy(
    maxAttempts: 5,
    baseDelaySeconds: 1.0,
)));

// Or turn it off entirely
new ClientConfig($token, retryPolicy: RetryPolicy::none());
```

## Rate limits

Quotas come from the account's MYBOX plan and are enforced per API, not in
aggregate. Daily counters reset each day, per-minute counters each minute.

| API | 30GB | 80GB | 180GB–330GB | 2TB | 5TB | 10TB | 20TB |
|---|---|---|---|---|---|---|---|
| Download | 500/day | 1,000/day | 1,000/day | 2,000/day | 5,000/day | 20,000/day | 50,000/day |
| Search | 10/min | 10/min | 30/min | 30/min | 30/min | 30/min | 30/min |
| Delete | 60/min | 60/min | 240/min | 240/min | 240/min | 240/min | 240/min |
| Restore | 180/min | 180/min | 240/min | 240/min | 240/min | 240/min | 240/min |
| Everything else | 60/min | 60/min | 240/min | 240/min | 240/min | 240/min | 240/min |

MYBOX also states that bursts it reads as abuse can restrict an account without
prior warning.

## Behaviour worth knowing

- **Trashing does not hide a resource from `get()`.** After `delete()` the id
  stays readable and only its `parentId` changes. Check a listing, not `get()`,
  to tell whether something was deleted.
- **Purging is eventually consistent.** Right after `trash()->purge()` the id
  answers for well under a second before it starts returning 404.
- **An interrupted upload locks the file.** Reserving another upload for it
  answers 423 `LockedException` until the server releases the transfer.

## What the API does not cover

- **Password-protected folders** (암호 폴더, available on 180GB and larger plans)
  and **folders shared with you** are not reachable through the Open API at all;
  they only appear in the desktop and mobile apps.
- An account that is over quota or under sanction cannot use its tokens.
- Calling the API with a token belonging to a dormant account reactivates it.

## Custom HTTP wiring

Discovery finds your PSR-18 client automatically, but you can pass one in —
useful for proxies, custom timeouts, or logging middleware:

```php
$mybox = new MyboxClient(
    new ClientConfig($token, userAgent: 'my-app/2.0'),
    httpClient: $myPsr18Client,
    requestFactory: $myPsr17Factory,
    streamFactory: $myPsr17Factory,
);
```

## Development

```bash
composer install
composer test        # unit tests, no network
composer analyse     # PHPStan level 9
composer cs          # coding standards
```

Integration tests run against a real account and are excluded by default:

```bash
MYBOX_PAT=mbx_pat_xxx composer test:integration
```

They work inside a dedicated `__php_mybox_sdk_test__` folder and clean up after
themselves, including emptying it from the trash.

## License

MIT. See [LICENSE](LICENSE).
