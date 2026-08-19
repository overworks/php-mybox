# Storage transfer protocol

The MYBOX Open API documents how to *reserve* a transfer URL but not how to
send bytes to it. This file records the format, and the behaviour around it,
as established against the live service on 2026-08-19.

## The upload call

`POST /v1/drive/files` returns a URL on a **separate host**:

```json
{"offset": 0, "uploadUrl": "https://open-api-fs.mybox.naver.com/v1/storage/upload?auth=4&stoken=…"}
```

Send the bytes there as follows.

```http
POST /v1/storage/upload?auth=4&stoken=… HTTP/1.1
Host: open-api-fs.mybox.naver.com
Content-Type: multipart/form-data; boundary=…
Content-Length: …

--…
Content-Disposition: form-data; name="Filedata"; filename="report.pdf"
Content-Type: application/octet-stream

<raw bytes>
--…--
```

```json
200 {"resourceId": "dXJsaW5lZXwzNDcy…", "name": "report.pdf", "fileSize": 11}
```

Four details are load-bearing:

- **POST only.** `PUT`, `GET` and `HEAD` are not routed and answer 404;
  `OPTIONS` answers 200.
- **`multipart/form-data` only.** Every other framing — raw octet-stream,
  `text/plain`, JSON, form-urlencoded, no Content-Type, chunked,
  `multipart/mixed`, `multipart/related`, multipart without a boundary — is
  rejected with `400 {"message":"Invalid Data Format"}`.
- **The part must be named `Filedata`**, with exactly that capitalisation. It
  is the legacy Flash-uploader convention Naver's storage tier still follows.
  `FileData`, `fileData` and `filedata` are all rejected with
  `400 {"message":"Param Not Exist"}`, as is every other name — roughly 450
  were tried before the right one was found.
- **No `Authorization` header.** The URL authenticates itself through `stoken`;
  corrupting or removing it gives `401 {"message":"Unauthorized"}`. Sending the
  personal access token to the storage host changes nothing and needlessly
  exposes it.

`auth` is a mode selector: `4` for upload, `3` for download. Reserved upload
URLs are **reusable** — they are not consumed on first touch.

Credit: the `Filedata` part name was confirmed against the
[MYBOX Sync Obsidian plugin](https://github.com/choihc/mybox-sync) (MIT), the
only public implementation of this call.

## The download call

`GET /v1/drive/files/{fileId}/download` returns
`{"downloadUrl": "…?auth=3&resourceKey=…&atoken=…", "expiresIn": 600}`.
Fetch it with a plain `GET` and no `Authorization` header. Verified byte-for-
byte for both ASCII and UTF-8 content.

## Behaviour worth knowing

**Size must be exact.** `fileSize` in the reservation must equal the bytes
actually sent. Sending fewer answers `500 {"message":"File Storage Error"}`.

**Resume does not appear to retain partial bytes.** The API exposes
`resume`/`modifiedTime` on the reservation and an `offset` in the response, and
the SDK implements both, but no partial upload could be made to produce a
non-zero offset:

- Sending a deliberately short body is treated as a size mismatch (500), not as
  a partial write.
- Cutting the socket mid-transfer makes the next reservation for that file
  answer `423 LOCKED` while the server still holds the interrupted transfer.
  Once the lock clears, the offset is back to `0` — the partial bytes were
  discarded.

So the `Content-Range` framing this SDK sends when `offset > 0` (the bare
`start-end/total` form, without the RFC 9110 `bytes ` prefix) is **not
verified**. Everything else on this page is.

**Trashing does not hide a resource from `get()`.** After
`DELETE /v1/drive/resources/{id}` the id stays readable; only its `parentId`
changes to a trash container. Use the folder listing or the trash listing to
tell whether something was deleted, not `get()`.

**Purging is eventually consistent.** Right after
`DELETE /v1/drive/trash/{id}` the id is still readable for well under a second,
reporting `size: 0`, before it starts answering 404.

## Swapping the format

If Naver changes the wire format, one class covers it — no other part of the
SDK knows about multipart:

```php
use Minhyung\Mybox\ClientConfig;
use Minhyung\Mybox\MyboxClient;
use Minhyung\Mybox\Transfer\UploadStrategy;

final class MyStrategy implements UploadStrategy { /* … */ }

$mybox = new MyboxClient(new ClientConfig($token), uploadStrategy: new MyStrategy());
```

## Reproducing

```bash
echo 'MYBOX_PAT=mbx_pat_…' > .env

php tools/verify-sdk.php        # drives the SDK's upload/download end to end
php tools/cleanup-sandbox.php   # removes any probe folder left behind
```

Both work inside `__php_mybox_sdk_probe__` / `__php_mybox_sdk_test__` and clean
up after themselves, trash included. `cleanup-sandbox.php` retries while a
resource is still locked.
