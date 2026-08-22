# Releasing

The release is driven by a **git tag**. Pushing a tag `X.Y.Z` on `main` runs
`.github/workflows/release.yml`, which checks its inputs, builds the installable
ZIP with `shopware-cli`, uploads it to the store, and publishes a GitHub release
with the ZIP attached and the notes from `.github/release-notes/X.Y.Z.md`.

The job runs in this order, and the order is load-bearing:

1. **Preflight** - every input that can be missing, checked before PHP is even
   installed: the notes file, `composer.json`'s `version` against the tag, and a
   `# X.Y.Z` section in both store changelogs. A release that cannot succeed is
   refused here, in seconds, with nothing built and nothing published.
2. Validate and build the ZIP.
3. **Upload to the store**, then **publish the GitHub release** - in that order,
   because a failed store upload is invisible and a GitHub release with no ZIP
   is not. See "Why the store upload goes first" below.
4. **Verify the published release carries its ZIP**, by asking the API rather
   than trusting the publish step.

## Steps

1. **Roll the changelogs** to the new version:
   - `CHANGELOG.md` - move the `[Unreleased]` section under `## [X.Y.Z] - <date>`.
   - `CHANGELOG_en-GB.md` and `CHANGELOG_de-DE.md` - add a `# X.Y.Z` entry
     (store format; the store validator requires a `CHANGELOG*.md` with the
     released version).
2. **Write the release notes** at `.github/release-notes/X.Y.Z.md`. This is the
   GitHub release body. Preflight **fails** if the file is missing or empty, so
   it can never publish an empty release.
   **Roll `composer.json`'s `version` to `X.Y.Z` in the same PR.** Shopware reads
   that field as the plugin version - the tag is not consulted - so a mismatch
   ships a plugin that reports itself as the previous release, with every step
   green. Preflight refuses the tag rather than let that out.
3. Open a PR with 1 and 2, merge to `main`.
4. **Tag on `main` and push:**
   ```bash
   git checkout main && git pull
   git tag X.Y.Z
   git push origin X.Y.Z
   ```
5. Watch the run: `gh run list --workflow=release.yml`. On success the release
   is published with `ShopwareCarve-X.Y.Z.zip` attached and the notes body.
   You do not have to keep watching: `.github/workflows/release-audit.yml` runs
   daily and fails if any published release is missing its ZIP.

## Notes source of truth

Release notes live **in the repo** (`.github/release-notes/`), not only on the
GitHub release object. This is deliberate: a release that is deleted or rebuilt
is always reproducible from git, and the tag alone yields a fully-populated
release. `softprops/action-gh-release` sets the body from `body_path`.

## Special cases and gotchas

- **Never `gh release delete` mid-flow.** The workflow's publish step attaches to
  (or creates) the release for the tag. If a run fails, **fix and re-run**
  (`gh run rerun <id>`) or move the tag - do not delete the release. Deleting it
  loses anything that lived only on the release object. (Notes are safe now that
  they live in the repo, but assets/state are not.)
- **The tag must point at a commit that already contains the fixes.** Re-running
  a failed run replays the workflow *as of the tagged commit*. If the fix landed
  after the tag, move the tag: delete it (`git push origin :refs/tags/X.Y.Z`),
  re-tag on the updated `main`, push again.
- **Store description length.** `shopware-cli extension validate` requires
  `extra.description` (en-GB and de-DE) in `composer.json` to be **150-185
  characters**. Too short/long fails the release at the validate step.
- **JS dependency must be on npm first.** The admin live preview depends on
  `@markup-carve/carve`. `shopware-cli extension zip` runs `npm install`, so that
  package must be published to npm **before** a shopware-carve release, or the
  ZIP build fails with a 404. Release carve-js first. That install now honors
  `src/Resources/app/administration/package-lock.json`, so the ZIP carries the
  engine CI measured rather than whatever the registry served at release time -
  bump the lockfile (`npm install` in that directory) as a deliberate step when a
  release should ship a newer engine.
- **The PHP lockfile is refreshed on the floor, not on your machine.**
  `composer.lock` is committed so CI can state which `markup-carve/carve-php` a
  green run measured (the `locked-install` job installs it and reads the version
  back). It is solved against PHP **8.2**, the floor `composer.json` declares, and
  it installs only there: `shopware/core` pulls in `lcobucci/clock`, which caps
  itself at 8.4. So refresh it with `composer update` under PHP 8.2 - a refresh on
  a newer PHP produces a lock the job cannot install. `composer validate
  --check-lock` runs in that job, so a range moved without a refresh is reported
  rather than ignored. The lock is deliberately NOT what the Shopware axis
  installs; those legs resolve per line (see `ci.yml`'s header).
- **Version moves only at release time, and then it must move.** Per the org
  convention the `version` field in `composer.json` is not bumped per feature
  PR - it changes when the maintainer cuts a release, in the same PR as the
  changelogs and the notes. It is not optional at that point: Shopware reads
  this field as the plugin version (unlike plain Composer libs where the tag
  drives it), so a tag ahead of it ships a mislabeled plugin. Preflight compares
  the two and refuses the tag.
- **Store upload is optional.** The `Upload to Shopware Community Store` step runs
  only when `SHOPWARE_CLI_ACCOUNT_EMAIL` / `SHOPWARE_CLI_ACCOUNT_PASSWORD` repo
  secrets are set; otherwise it self-skips and only the GitHub release is produced.
- **Packagist** is not automatic. Submit the package once at packagist.org; it
  then auto-indexes future tags for `composer require markup-carve/shopware-carve`.

## Why the store upload goes first

The store upload runs **before** the GitHub release is published, which reads
backwards until you ask which failure anyone can see.

A store upload that fails on its own - bad credentials, a store-side rejection,
a network blip - used to leave a GitHub release that was published, had its ZIP
attached and looked completely finished, while no merchant had received
anything. Nothing anywhere could tell that apart from a release that shipped.

Now the invisible step goes first and the detectable one goes last. If the store
upload fails, the GitHub release never gets its ZIP, and the asset audit sees a
published release with nothing attached and says so. The failure ends up in the
one place something is watching, instead of being hidden behind a green release
page.

**What that costs, and the lever for it.** A store submission is not
idempotent. If the upload succeeds and the publish step then fails, re-running
the tag would try to submit the same version again and can be rejected as a
duplicate - so the run would never reach the publish step, and the release could
not be completed by re-running. No ordering fixes that; it is a property of the
external submission. So when you hit it, set the repository variable
`SKIP_STORE_UPLOAD` to `true`, re-run, and the job goes straight to publishing
the ZIP the store already has. Unset it afterwards.

```bash
gh variable set SKIP_STORE_UPLOAD --body true
gh run rerun <id>
gh variable delete SKIP_STORE_UPLOAD
```

**As of this writing the store step has never actually run.** It self-skipped on
every release including 0.1.0 and 0.1.1, because `SHOPWARE_CLI_ACCOUNT_EMAIL` and
`SHOPWARE_CLI_ACCOUNT_PASSWORD` are not configured on this repository - so
nothing has ever been pushed to the Community Store by this workflow. Set the
secrets when that should start happening.

## The asset audit

`.github/scripts/check-release-assets.sh` asks the releases API whether every
**published** release carries a `*.zip`. Drafts are excluded on purpose: a draft
with no asset is a release being prepared, a published one with no asset is a
release that lied.

**0.1.2 is exempt, by name, and it is the only exemption.** It is listed in the
script's `superseded_releases` table with its reason, so the daily job passes
while still printing a `skip` line that says 0.1.2 ships nothing. Any other
assetless published release fails exactly as before, a tag that merely looks
like it (`0.1.20`) gets no exemption, and the entry itself fails once no
published release with that tag exists. Adding a line to that table is a
maintainer decision about one specific release - it is never the way to quiet a
failing audit, because the audit failing is the only signal that a release is
not installable.

`.github/scripts/check-release-assets.test.sh` is what keeps that honest. It
runs the script against canned listings it will never meet in production - a
second assetless release, a look-alike tag, an exemption pointing at a release
that has gone - and asserts it still refuses them. The audit workflow runs it
every morning before it trusts the live answer, because a green audit says
nothing about whether the check can still say no. Run it by hand with
`.github/scripts/check-release-assets.test.sh`; it needs only `bash` and `jq`.

Run it by hand any time - it needs nothing but `gh`:

```bash
.github/scripts/check-release-assets.sh            # every published release
.github/scripts/check-release-assets.sh --tag 0.1.1
```

`.github/workflows/release-audit.yml` runs it daily and on demand
(`gh workflow run release-audit.yml -f tag=X.Y.Z`), and `release.yml` runs the
same script scoped to the tag it just published.

**This exists because of 0.1.2.** That run built the ZIP, failed at its notes
step, and skipped both publish steps - but the release object already existed,
created by hand ten hours earlier through the draft-first flow below. What was
left was a published release with a tag, a body and no ZIP, indistinguishable
from one that shipped. It stayed that way for eight weeks, and no merchant ever
received 0.1.2 or the carve-php security floor it carried.

**That was ruled: 0.1.2 is superseded, not re-run.** It keeps its page, its tag
and its body - amended to say plainly that nothing shipped from it - and 0.1.3
carries its whole content, the carve-php 0.1.5 requirement included, so a shop
going from 0.1.1 to 0.1.3 misses nothing. Do not re-run the 0.1.2 tag: it would
attach an artifact under a body stating none was ever attached, and leave the
audit's exemption describing something untrue.

If it fails, the release is not installable. Re-run the release workflow for that
tag (see the re-run note above), unpublish the release, or - as with 0.1.2 -
supersede it deliberately: fold its content into the next version, say so on its
page, and name it in `superseded_releases`. Leaving a published release that
ships nothing, with nothing recording that, is the failure itself and not a
cosmetic one.

## Draft-first alternative

If you prefer to review the rendered notes before publishing, create a **draft**
release for the tag first (`gh release create X.Y.Z --draft --notes-file
.github/release-notes/X.Y.Z.md`), then push the tag. `softprops` updates the
existing release and publishes it - it does not create a duplicate.
