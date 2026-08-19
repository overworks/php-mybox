# Notes for coding agents

Read [CONTRIBUTING.md](CONTRIBUTING.md) first — it is the full guide, and
everything below is a pointer to the parts most easily got wrong.

## Before you say a change is done

```bash
composer test && composer analyse && composer cs
```

All three, actually run. `composer test` needs no network.

## Hard rules

- **Never commit `.env`, a personal access token, or any value starting
  `mbx_pat_`.** Do not print one in tool output either.
- **Never silence PHPStan.** No `@phpstan-ignore`, no baseline, no inline
  `@var`, no cast added to quiet a report. Fix the underlying type hole.
- **The integration suite and everything in `tools/` hit a real MYBOX
  account.** Ask before running them. They create, upload to and delete real
  files. Work only inside the sandbox folder, and clean up afterwards —
  `php tools/cleanup-sandbox.php` if a run died partway.
- **Do not deliberately trip a rate limit.** MYBOX restricts accounts it reads
  as abusive, without warning.
- **Commits use Conventional Commits and carry no `Co-Authored-By:` trailer.**

## Things that look like bugs but are not

The API has behaviour Naver does not document, recorded in
[docs/transfer-protocol.md](docs/transfer-protocol.md). Check there before
"fixing" any of it:

- A trashed resource is still readable by id; only its `parentId` changes.
- Purging is eventually consistent — the id answers for a moment, then 404s.
- An interrupted upload locks the file, and re-reserving it returns 423.
- The upload part name must be exactly `Filedata`. Any other casing is a 400.
- `resume` is implemented but unverified; no interrupted upload has been
  observed to leave a non-zero offset.

## When you touch prose

[README.md](README.md) and [README.ko.md](README.ko.md) mirror each other.
Update both, or neither.
