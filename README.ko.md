# php-mybox

[![Packagist](https://img.shields.io/packagist/v/minhyung/mybox.svg)](https://packagist.org/packages/minhyung/mybox)
[![PHP](https://img.shields.io/packagist/dependency-v/minhyung/mybox/php.svg)](https://packagist.org/packages/minhyung/mybox)
[![CI](https://github.com/overworks/php-mybox/actions/workflows/ci.yml/badge.svg)](https://github.com/overworks/php-mybox/actions/workflows/ci.yml)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%209-brightgreen.svg)](phpstan.neon.dist)
[![License](https://img.shields.io/packagist/l/minhyung/mybox.svg)](LICENSE)

[네이버 MYBOX Open API](https://developers.mybox.naver.com/)용 PHP SDK입니다.
프레임워크에 의존하지 않습니다.

```bash
composer require minhyung/mybox
```

English documentation: [README.md](README.md)

- 공개된 20개 엔드포인트를 모두 지원하며, 각각 타입이 지정된 메서드 하나로 대응
- PSR-18 / PSR-17 기반 — HTTP 클라이언트를 강제하지 않음
- 연관 배열 대신 readonly 모델과 enum 사용
- 커서 페이징 자동 처리, 업로드·다운로드 모두 스트리밍
- 재시도할 가치가 있는 상태 코드에만 자동 백오프, 에러 코드별 전용 예외

| | |
| --- | --- |
| **시작하기** | [요구 사항](#요구-사항) · [설치](#설치) · [개인 액세스 토큰 발급](#개인-액세스-토큰-발급) · [빠르게 시작하기](#빠르게-시작하기) |
| **레퍼런스** | [엔드포인트](#엔드포인트) · [목록 조회와 정렬](#목록-조회와-정렬) · [업로드](#업로드) · [다운로드](#다운로드) · [검색](#검색) · [경로로 찾기](#경로로-찾기) |
| **운영** | [에러 처리](#에러-처리) · [재시도](#재시도) · [API 사용 한도](#api-사용-한도) · [알아둘 동작](#알아둘-동작) · [미지원 범위](#open-api가-지원하지-않는-범위) · [HTTP 계층 직접 지정](#http-계층-직접-지정) |

## 요구 사항

PHP 8.2 이상.

## 설치

```bash
composer require minhyung/mybox
```

프로젝트에 이미 있는 [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP
클라이언트를 그대로 사용하며,
[php-http/discovery](https://docs.php-http.org/en/latest/discovery.html)가
알아서 찾아냅니다 — 별도 배선이 필요 없습니다. 아직 없다면 아무거나 하나:

```bash
composer require guzzlehttp/guzzle      # symfony/http-client, kriswallsmith/buzz 등도 가능
```

## 개인 액세스 토큰 발급

1. MYBOX 웹에서 네이버 아이디로 로그인합니다.
2. **설정 → 계정 및 개인 액세스 토큰 관리**에서 **토큰 생성**을 클릭합니다.
3. 이름과 유효기간(30/60/90/180일)을 설정합니다.
4. 토큰은 생성 시 **한 번만** 노출되므로 바로 복사해 보관하세요.

계정당 최대 5개까지 만들 수 있습니다. 토큰이 유출되면 제3자가 내 드라이브 전체에
접근할 수 있으니 비밀번호처럼 다루세요.

## 빠르게 시작하기

```php
use Minhyung\Mybox\MyboxClient;

$mybox = MyboxClient::create($_ENV['MYBOX_PAT']);

// 남은 용량 확인
$storage = $mybox->drive()->storage();
printf("%.1f GB / %.1f GB 사용 중\n", $storage->usedBytes / 1e9, $storage->quotaBytes / 1e9);

// 폴더를 만들고 파일 업로드
$folder = $mybox->files()->createFolder('업무자료');
$mybox->upload()->fromFile('/tmp/report.pdf', parentId: $folder->resourceId);

// 폴더 전체를 페이지 단위로 가져오되 메모리에는 한 번에 올리지 않음
foreach ($mybox->drive()->listFolderAll($folder->resourceId) as $item) {
    printf("%-40s %10d bytes\n", $item->name, $item->size);
}
```

## 엔드포인트

공식 문서의 분류를 그대로 따릅니다.

### `drive()` — 조회와 즐겨찾기

| 메서드 | 엔드포인트 |
|---|---|
| `storage()` | `GET /v1/drive/storage` |
| `setTrashAutoDeleteDays(int $days)` | `PATCH /v1/drive/storage` |
| `listRoot(?ListOptions)` | `GET /v1/drive/resources` |
| `listFolder(string $folderId, ?ListOptions)` | `GET /v1/drive/folders/{folderId}/resources` |
| `get(string $resourceId)` | `GET /v1/drive/resources/{resourceId}` |
| `favorite(string $resourceId)` | `POST /v1/drive/resources/{resourceId}/favorite` |
| `unfavorite(string $resourceId)` | `POST /v1/drive/resources/{resourceId}/unfavorite` |

`listRootAll()`, `listFolderAll()`은 같은 데이터를 페이지네이터로 돌려줍니다.

### `files()` — 변경과 전송 URL

| 메서드 | 엔드포인트 |
|---|---|
| `createFolder(string $name, ?string $parentId)` | `POST /v1/drive/folders` |
| `createUploadUrl(UploadRequest)` | `POST /v1/drive/files` |
| `createDownloadUrl(string $fileId)` | `GET /v1/drive/files/{fileId}/download` |
| `copy(string $resourceId, ?CopyOptions)` | `POST /v1/drive/resources/{resourceId}/copy` |
| `delete(string $resourceId)` | `DELETE /v1/drive/resources/{resourceId}` |
| `move(string $resourceId, string $parentId, bool $isOverwrite)` | `POST /v1/drive/resources/{resourceId}/move` |
| `rename(string $resourceId, string $name)` | `POST /v1/drive/resources/{resourceId}/rename` |

### `search()` — 검색

| 메서드 | 엔드포인트 |
|---|---|
| `files(SearchFilesOptions)` | `GET /v1/search/resources/files` |
| `folders(SearchFoldersOptions)` | `GET /v1/search/resources/folders` |

`filesAll()`, `foldersAll()`이 결과를 페이징합니다.

### `trash()` — 휴지통

| 메서드 | 엔드포인트 |
|---|---|
| `list(?TrashListOptions)` | `GET /v1/drive/trash` |
| `restore(string $resourceId, bool $isOverwrite)` | `POST /v1/drive/trash/{resourceId}/restore` |
| `purge(string $resourceId)` | `DELETE /v1/drive/trash/{resourceId}` |
| `empty()` | `DELETE /v1/drive/trash` |

`listAll()`이 휴지통 전체를 페이징합니다.

## 목록 조회와 정렬

```php
use Minhyung\Mybox\Model\Enum\SortField;
use Minhyung\Mybox\Model\Enum\SortOrder;
use Minhyung\Mybox\Request\ListOptions;

$page = $mybox->drive()->listRoot(new ListOptions(
    sortBy: SortField::ModifiedAt,
    sortOrder: SortOrder::Desc,
    count: 500,          // 1~1000, 기본값 100
));

$page->fileCount;        // 이 폴더 바로 안의 파일 수
$page->subFolderCount;   // 이 폴더 바로 아래의 폴더 수
$page->nextCursor();     // 마지막 페이지면 null
```

정렬 기준과 무관하게 MYBOX는 항상 폴더를 파일보다 먼저 나열합니다.

### 페이징

```php
$all = $mybox->drive()->listRootAll();

foreach ($all as $item) { /* 항목 단위 순회, 페이지는 필요할 때 가져옴 */ }
foreach ($all->pages() as $page) { /* 페이지 단위 메타데이터 */ }

$all->take(50);   // 50개를 채우는 순간 추가 요청 중단
$all->all();      // 전체를 한 번에
```

## 업로드

```php
// 로컬 파일 — URL 발급과 바이트 전송을 한 번에 처리
$mybox->upload()->fromFile('/tmp/report.pdf', parentId: $folderId, isOverwrite: true);

// 메모리에서
$mybox->upload()->fromString($csv, 'export.csv', parentId: $folderId);

// 열린 스트림에서 (정확한 길이를 알 때)
$mybox->upload()->fromStream($handle, 'video.mp4', $size, parentId: $folderId);

// MYBOX가 중단된 업로드의 일부를 갖고 있는지 확인하고 그 지점부터 이어서
$mybox->upload()->fromFile('/tmp/big.zip', resume: true);
```

모두 `UploadResult`를 돌려주며 저장된 파일의 `resourceId`, `name`, `fileSize`를
담고 있습니다. 업로드는 스트리밍이라 10GB 파일도 10KB 파일과 메모리 사용량이
같습니다.

선언한 크기는 실제 바이트 수와 정확히 일치해야 합니다. MYBOX가 그 값으로 업로드를
예약하며, 불일치 시 500으로 응답합니다. 파일 하나의 최대 크기는 `storage()`의
`maxFileBytes`입니다.

실제 바이트 전송은 별도 스토리지 호스트로 가는 두 번째 호출이며, 그 규약은 공식
문서에 없습니다. 실제 서비스를 상대로 확인한 내용을
[docs/transfer-protocol.md](docs/transfer-protocol.md)에 정리해 두었습니다.
`resume`만은 확인하지 못했습니다 — 중단된 업로드가 0이 아닌 offset을 남기는
경우를 재현할 수 없었습니다.

## 다운로드

```php
$mybox->download()->toFile($fileId, '/tmp/report.pdf');   // 디스크로 스트리밍
$mybox->download()->contents($fileId);                    // 메모리로
$stream = $mybox->download()->open($fileId);              // PSR-7 스트림
```

호출할 때마다 새 URL을 발급받습니다. 문서상 1회용이며 10분간 유효합니다.
다운로드도 스트리밍이므로 `toFile()`은 메모리가 아니라 디스크 용량에만
제약됩니다.

## 검색

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

검색에는 `q`, `category`, 날짜 범위 중 최소 하나가 필요합니다. SDK가 빈 조건을
로컬에서 먼저 걸러내므로, 빡빡한 검색 한도를 헛되이 소모하지 않습니다. 페이지
크기는 목록 조회와 달리 20~200입니다.

## 경로로 찾기

MYBOX는 모든 것을 ID로 다룹니다. 경로에서 출발하려면:

```php
$folderId = $mybox->paths()->folderId('/문서/2026');
$fileId   = $mybox->paths()->fileId('/문서/2026/회의록.pdf');
```

폴더는 보통 검색 한 번으로 해석됩니다. 색인이 아직 갱신되지 않았다면 루트부터
목록 조회로 걸어 내려갑니다. 결과는 클라이언트 인스턴스 단위로 캐시되므로, 이름
변경이나 이동 후에는 `clearCache()`를 호출하세요.

## 에러 처리

모든 실패는 `MyboxException`입니다. API가 반환한 에러는 `ApiException` 하위
클래스이며, 고객센터 문의 시 필요한 `requestId`를 담고 있습니다.

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
    // 507 — 용량 초과
} catch (ApiException $e) {
    error_log("{$e->errorCode} {$e->errorMessage} (requestId {$e->requestId})");
}
```

| HTTP | 예외 |
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

연결 실패와 파싱 불가 응답은 `TransportException`, 요청 전에 거부된 인자는
`InvalidArgumentException`입니다.

## 재시도

429, 502, 503은 지수 백오프와 full jitter로 3회까지 재시도하며, 서버가
`Retry-After`를 보내면 그 값을 우선합니다. 500은 재시도하지 않습니다 — MYBOX가
일시적 혼잡이 아니라 실제 서버 오류에 이 코드를 쓰기 때문입니다.

```php
use Minhyung\Mybox\ClientConfig;
use Minhyung\Mybox\Http\RetryPolicy;
use Minhyung\Mybox\MyboxClient;

$mybox = new MyboxClient(new ClientConfig($token, retryPolicy: new RetryPolicy(
    maxAttempts: 5,
    baseDelaySeconds: 1.0,
)));

// 완전히 끄려면
new ClientConfig($token, retryPolicy: RetryPolicy::none());
```

## API 사용 한도

한도는 계정의 요금제를 따르며, 전체 합산이 아니라 API별로 적용됩니다. 일 한도는
매일, 분 한도는 매분 갱신됩니다.

| API | 30GB | 80GB | 180GB~330GB | 2TB | 5TB | 10TB | 20TB |
|---|---|---|---|---|---|---|---|
| 다운로드 | 500회/일 | 1,000회/일 | 1,000회/일 | 2,000회/일 | 5,000회/일 | 20,000회/일 | 50,000회/일 |
| 검색 | 10회/분 | 10회/분 | 30회/분 | 30회/분 | 30회/분 | 30회/분 | 30회/분 |
| 삭제 | 60회/분 | 60회/분 | 240회/분 | 240회/분 | 240회/분 | 240회/분 | 240회/분 |
| 복원 | 180회/분 | 180회/분 | 240회/분 | 240회/분 | 240회/분 | 240회/분 | 240회/분 |
| 그 외 | 60회/분 | 60회/분 | 240회/분 | 240회/분 | 240회/분 | 240회/분 | 240회/분 |

MYBOX는 단시간 대량 호출이나 어뷰징이 감지되면 사전 경고 없이 이용을 제한할 수
있다고 명시하고 있습니다.

## 알아둘 동작

- **휴지통으로 옮겨도 `get()`에서는 계속 보입니다.** `delete()` 후에도 ID로 조회가
  되며 `parentId`만 휴지통 컨테이너로 바뀝니다. 삭제 여부는 `get()`이 아니라 목록
  조회로 판단하세요.
- **영구 삭제는 최종적 일관성입니다.** `trash()->purge()` 직후 1초도 안 되는 동안은
  여전히 조회되다가 404로 바뀝니다.
- **업로드가 중단되면 파일이 잠깁니다.** 같은 파일의 업로드를 다시 예약하면 서버가
  중단된 전송을 놓아줄 때까지 423 `LockedException`이 발생합니다.

## Open API가 지원하지 않는 범위

- **암호 폴더**(180GB 이상 요금제)와 **공유받은 폴더**는 Open API로 접근할 수
  없습니다. PC 웹과 모바일 앱에서만 확인됩니다.
- 용량 초과나 징계 처리된 계정의 토큰으로는 호출이 실패합니다.
- 휴면 계정의 토큰으로 호출하면 서비스 로그인으로 간주되어 휴면이 해제됩니다.

## HTTP 계층 직접 지정

Discovery가 PSR-18 클라이언트를 자동으로 찾지만, 프록시·타임아웃·로깅
미들웨어가 필요하면 직접 주입할 수 있습니다.

```php
$mybox = new MyboxClient(
    new ClientConfig($token, userAgent: 'my-app/2.0'),
    httpClient: $myPsr18Client,
    requestFactory: $myPsr17Factory,
    streamFactory: $myPsr17Factory,
);
```

## 기여하기

```bash
composer install
composer test        # 단위 테스트, 네트워크 불필요
composer analyse     # PHPStan level 9
composer cs          # 코딩 스타일 검사
```

프로젝트 구조, 코딩 규약, 엔드포인트 추가 방법, 그리고 실제 계정을 사용하는
통합 테스트(기본적으로 `composer test`에서 제외됨)의 취급 규칙은
[CONTRIBUTING.md](CONTRIBUTING.md)에 정리되어 있습니다.

## 변경 이력

[CHANGELOG.md](CHANGELOG.md)를 참고하세요.

## 라이선스

MIT. [LICENSE](LICENSE)를 참고하세요.
