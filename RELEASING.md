# Releasing

The release is driven by a **git tag**. Pushing a tag `X.Y.Z` on `main` runs
`.github/workflows/release.yml`, which validates the plugin, builds the
installable ZIP with `shopware-cli`, and publishes a GitHub release with the
ZIP attached and the notes from `.github/release-notes/X.Y.Z.md`.

## Steps

1. **Roll the changelogs** to the new version:
   - `CHANGELOG.md` - move the `[Unreleased]` section under `## [X.Y.Z] - <date>`.
   - `CHANGELOG_en-GB.md` and `CHANGELOG_de-DE.md` - add a `# X.Y.Z` entry
     (store format; the store validator requires a `CHANGELOG*.md` with the
     released version).
2. **Write the release notes** at `.github/release-notes/X.Y.Z.md`. This is the
   GitHub release body. The workflow **fails** if the file is missing, so it can
   never publish an empty release.
3. Open a PR with 1 and 2, merge to `main`.
4. **Tag on `main` and push:**
   ```bash
   git checkout main && git pull
   git tag X.Y.Z
   git push origin X.Y.Z
   ```
5. Watch the run: `gh run list --workflow=release.yml`. On success the release
   is published with `ShopwareCarve-X.Y.Z.zip` attached and the notes body.

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
  ZIP build fails with a 404. Release carve-js first.
- **Version is frozen until a real release.** Per the org convention, the
  `version` field in `composer.json` stays at its initial value until the
  maintainer explicitly cuts a release. Shopware reads this field as the plugin
  version (unlike plain Composer libs where the tag drives it).
- **Store upload is optional.** The `Upload to Shopware Community Store` step runs
  only when `SHOPWARE_CLI_ACCOUNT_EMAIL` / `SHOPWARE_CLI_ACCOUNT_PASSWORD` repo
  secrets are set; otherwise it self-skips and only the GitHub release is produced.
- **Packagist** is not automatic. Submit the package once at packagist.org; it
  then auto-indexes future tags for `composer require markup-carve/shopware-carve`.

## Draft-first alternative

If you prefer to review the rendered notes before publishing, create a **draft**
release for the tag first (`gh release create X.Y.Z --draft --notes-file
.github/release-notes/X.Y.Z.md`), then push the tag. `softprops` updates the
existing release and publishes it - it does not create a duplicate.
