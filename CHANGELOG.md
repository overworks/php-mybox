# Changelog

This project follows [Semantic Versioning](https://semver.org/). While the
major version is `0`, a minor bump may carry a breaking change.

## 0.1.1 — 2026-08-19

### Fixed

- **Resuming an interrupted upload never actually resumed outside KST.** MYBOX
  identifies an interrupted upload by the literal `modifiedTime` string the
  reservation carried, and only recognises the `+09:00` spelling — the same
  instant written as `+00:00` is treated as a different file. Because
  `Uploader::fromFile()` formatted that string in the system timezone,
  `resume: true` silently restarted the upload on any host not set to
  Asia/Seoul. `UploadRequest` now converts to KST before sending, whatever
  timezone the caller uses.

### Documented

Resumable uploads are now verified end to end, and
[docs/transfer-protocol.md](docs/transfer-protocol.md) records what governs
them: `isOverwrite` suppresses the offset, a short body is rejected as a size
mismatch rather than accepted as a partial write, and a reservation answers
`423 LOCKED` for about two seconds after the connection dies before the offset
appears.

## 0.1.0 — 2026-08-19

First release. Covers the Naver MYBOX Open API as published at
<https://developers.mybox.naver.com/>.

### Added

- All 20 documented endpoints, one typed method each, grouped as
  `drive()`, `files()`, `search()` and `trash()`.
- PSR-18 / PSR-17 based transport. The library depends on the interfaces and
  `php-http/discovery`, never on a concrete HTTP client.
- Readonly response models and backed enums, with `DateTimeImmutable`
  timestamps. Listing, trash and search results are modelled separately, since
  MYBOX returns a different projection for each.
- Request option objects that validate MYBOX's constraints before a call goes
  out: page sizes, the trash auto-delete interval, and the requirement that a
  search carry at least one criterion.
- `CursorPaginator`, shared by every listing. It fetches pages lazily as
  iteration advances and stops if the server repeats a cursor.
- Streaming upload and download. `MultipartStream` wraps a payload in its
  envelope without buffering, so file size is bounded by disk rather than by
  memory.
- Path-to-id resolution via `paths()`, resolving a folder in one search call
  and falling back to walking down from the root.
- One exception type per documented error status, each carrying the `code`,
  `message`, `requestId` and `timestamp` from the response body.
- Retries with exponential backoff and full jitter on 429, 502 and 503,
  honouring `Retry-After`. 500 is excluded deliberately. A retried request
  replays from where its body started, so resuming an upload cannot resend the
  whole file.

### Documented

The storage transfer calls are absent from Naver's documentation. What probing
the live service established is recorded in
[docs/transfer-protocol.md](docs/transfer-protocol.md): the upload wire format,
that reserved upload URLs are reusable, and behaviour the API docs do not
mention — a trashed resource stays readable by id, purging is eventually
consistent, and an interrupted upload locks the file.

### Known limitations

- Password-protected folders and folders shared with the account are not
  reachable through the Open API, and so not through this SDK.
