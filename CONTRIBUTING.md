# Contributing to php-mybox

Thanks for helping out. This file is the single source of truth for how the
project is built and what is expected of a change; [AGENTS.md](AGENTS.md) and
[CLAUDE.md](CLAUDE.md) point here rather than repeating it.

## Getting set up

```bash
git clone https://github.com/overworks/php-mybox.git
cd php-mybox
composer install
```

PHP 8.2 or newer. `composer install` pulls Guzzle as the PSR-18 implementation
used by the test suite; the library itself never depends on a concrete client.

## The three checks

Every change has to pass all three. CI runs them on PHP 8.2, 8.3 and 8.4.

```bash
composer test      # PHPUnit, no network
composer analyse   # PHPStan level 9 over src and tests
composer cs        # PHP CS Fixer, PSR-12 plus the rules in .php-cs-fixer.dist.php
```

`composer cs:fix` applies the formatting rather than just reporting it.

**Do not silence PHPStan.** No `@phpstan-ignore` comments, no baseline entries,
no inline `@var` to override an inferred type, no casts added purely to quiet a
report. A level 9 complaint is nearly always a real hole — the fix belongs in
the code. Where `json_decode` genuinely produces an untypable value, narrow it
through [`Support\Json`](src/Support/Json.php) instead of asserting around it.

## Integration tests

The unit suite is hermetic. The integration suite talks to a real MYBOX account
and is excluded from `composer test`:

```bash
echo 'MYBOX_PAT=mbx_pat_…' > .env      # .env is gitignored
composer test:integration
```

Rules for anything that touches a live account:

- Work only inside the sandbox folder the suite creates
  (`__php_mybox_sdk_test__`, or `__php_mybox_sdk_probe__` for the tools). Never
  read, move or delete a resource outside it.
- Clean up, trash included. If a run dies partway, `php tools/cleanup-sandbox.php`
  removes what was left; it retries while a resource is still locked.
- Never commit a token, paste one into a commit message, or echo one in output
  a tool prints.
- Do not deliberately exhaust a rate limit. MYBOX states that bursts it reads
  as abuse can restrict an account without prior warning, and search allows as
  few as 10 calls a minute.

## Layout

```
src/
  MyboxClient.php     entry point; wires the groups together
  ClientConfig.php    token, base URI, retry policy, user agent
  Http/               Transport — the only place the SDK speaks HTTP
  Api/                one class per endpoint group, one method per endpoint
  Model/              readonly response objects and enums
  Request/            option objects, validated on construction
  Pagination/         CursorPaginator, shared by every listing
  Transfer/           upload and download, and the storage wire format
  Path/               path-to-id resolution
  Exception/          one type per documented error status
  Support/            internal helpers
tests/Unit/           hermetic, fixture-driven
tests/Integration/    live, opt-in, sandboxed
tools/                scripts that exercise or clean up a real account
docs/                 findings that are not in Naver's documentation
```

## Conventions

- `declare(strict_types=1)` in every file.
- Models are `final`, readonly, and built through a static `fromArray()`. A
  required field that is missing or mistyped raises `TransportException` rather
  than coercing to a default — a response this SDK does not understand should
  be loud.
- Parse through [`Model\Hydrator`](src/Model/Hydrator.php) so the failure
  message names the field.
- Validate in the request option's constructor, not in the API method. Search
  quota is tight enough that rejecting an impossible query locally is worth
  more than the round trip.
- Comments explain *why*. The endpoint a method calls is already in its
  signature; what belongs in prose is the constraint that is not obvious —
  a quota, an ordering guarantee, a field MYBOX omits for files.
- Public API gets PHPDoc in English. Prose documentation is bilingual
  ([README.md](README.md) and [README.ko.md](README.ko.md)); update both.

## Adding an endpoint

1. Add the method to the matching `Api/` class, taking an option object if it
   has more than a couple of parameters.
2. Add or extend a model in `Model/`, with the response example from Naver's
   documentation saved to `tests/Fixtures/`.
3. Add a unit test asserting the request URI, method and body, and the
   deserialised result.
4. If it is a listing, expose an `…All()` variant returning a
   `CursorPaginator`.
5. Add a row to the endpoint tables in both READMEs.

## Undocumented behaviour

Naver's documentation stops short of the storage transfer calls and says
nothing about several behaviours the SDK has to handle. What has been
established against the live service — the upload format, the resource
lifecycle quirks, the file lock after an interrupted upload — lives in
[docs/transfer-protocol.md](docs/transfer-protocol.md).

If you discover something new, record it there with what you observed, and say
plainly which parts are verified and which are inferred. A confident guess in
that file is worse than an admitted gap.

## Commits

[Conventional Commits](https://www.conventionalcommits.org/): `feat:`, `fix:`,
`docs:`, `refactor:`, `test:`, `chore:`.

Write the body for someone reading `git log` in a year: what changed and why,
not a restatement of the diff. **Do not add `Co-Authored-By:` trailers.**

Branch off `0.x`; open a pull request rather than pushing to it directly.
```
feat: page through trash cursors transparently

Every listing endpoint is cursor-paginated the same way, so the trash reuses
CursorPaginator rather than growing its own loop.
```
