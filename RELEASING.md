# Releasing

Release preparation starts from a reviewed commit on `main`. The
`release.yml` workflow runs its checks before creating a tag. Pull requests
also run `shopware-cli extension validate` using the same CLI version, pinned
in `.github/actions/shopware-cli/action.yml`.

The workflow runs in this order:

1. Verify the requested version, changelogs and unpublished GitHub draft. The
   draft must have notes and target the exact commit running the workflow.
2. Validate the extension and build its ZIP.
3. Wait for approval in the `release` environment. Recheck the version, draft
   and dispatched commit, then create the tag.
4. Build from that tag, upload to the Store when configured, and publish the
   GitHub release with its ZIP.
5. Read the release back and verify that its ZIP exists.

## Steps

1. Roll `composer.json`'s version and the three changelogs in a PR. The Store
   changelogs need a `# X.Y.Z` section. Merge after the checks pass.
2. Resolve the intended full commit SHA from `main` and prepare the GitHub
   draft with its release notes:

   ```bash
   gh release create X.Y.Z --draft --target COMMIT_SHA --notes-file /tmp/notes.md
   ```

   Read it back through the API and verify `tag_name`, `draft: true`, the notes
   and `target_commitish`. Updating a draft must explicitly retain the intended
   tag name. Release notes stay on the draft rather than in the repository.
3. When publication is authorized, dispatch from `main` while it still points
   at that commit:

   ```bash
   gh workflow run release.yml --ref main -f version=X.Y.Z
   ```

   Verify the run's `head_sha` against the intended commit. Leave tag creation
   to the workflow. It refuses an existing tag. Later changes to `main` do
   not change the dispatched commit; dispatch a fresh run to release newer code.
4. Watch `gh run list --workflow=release.yml` and approve the pending release
   environment when ready. On success, verify the published notes and
   `ShopwareCarve-X.Y.Z.zip`. The daily asset audit checks published releases too.

## Notes source of truth

The **draft release** holds the notes. They are not committed to the repository:
a file in git is a second copy of what GitHub already stores on the release
object, the two drift, and the copy nobody reads is the one in git. The draft is
also the review surface, so the notes get read before they are public rather
than after a merge.

The release job reads the draft's body and hands it to
`softprops/action-gh-release` as `body_path`, via a file under `RUNNER_TEMP`.

## Special cases and gotchas

- **Preserve a failed release's notes and assets.** Re-run a failed job when its
  inputs are still correct. If its tag points at old code, choose a correction
  or a new version explicitly before changing the published tag or release.
  A new release dispatch cannot repair an existing tag automatically.
- **Store description length.** `shopware-cli extension validate` requires
  `extra.description` (en-GB and de-DE) in `composer.json` to be **150-185
  characters**. Too short/long fails the release at the validate step.
- **Build the administration assets.** The editor uses the PHP preview endpoint.
  It does not depend on a separate carve-js release. Run the administration CI
  checks before packaging the plugin.
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
  drives it), so a tag ahead of it ships a mislabeled plugin. Verification compares
  the requested version with this field before creating the tag.
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
the publish job would try to submit the same version again and can be rejected as a
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

If it fails, the release is not installable. Re-run the failed publish job with its original
inputs (see the retry note above), unpublish the release, or - as with 0.1.2 -
supersede it deliberately: fold its content into the next version, say so on its
page, and name it in `superseded_releases`. Leaving a published release that
ships nothing, with nothing recording that, is the failure itself and not a
cosmetic one.

## The draft is required, not optional

The draft release must exist before dispatch; verification refuses to build
without one. `softprops` updates that existing release and
publishes it rather than creating a duplicate.
