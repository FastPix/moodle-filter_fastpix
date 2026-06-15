# Changelog

All notable changes to `filter_fastpix` are documented here. The format is
based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this
project follows [Semantic Versioning](https://semver.org/).

## [1.0.3] — 2026-06-15

### Changed
- **Video player is now served from your Moodle site, not a public CDN.** The
  FastPix player and the HLS library are loaded from the locally-vendored copies
  shipped with `local_fastpix` and `mod_fastpix` (ADR-017), so embeds no longer
  fetch any JavaScript from jsdelivr. This meets the Moodle Plugins Directory
  requirement that all JavaScript be served from the Moodle site.

### Fixed
- **Embeds no longer break against the latest companion plugins.** `mod_fastpix`
  removed its `PLAYER_LIB_URL` constant when the player moved to `local_fastpix`;
  the filter now consumes the new `local_fastpix` player surface instead, and
  resolves the HLS library to an absolute URL so embeds work on Moodle sites
  installed in a subdirectory.

### Fixed (Plugins Directory compliance)
- **No more inline CSS.** The player wrapper's `style="…"` attribute and the
  element styling set in JavaScript were moved into a new `styles.css` scoped to
  `.filter_fastpix-player-wrapper`. Inline CSS is forbidden by the Moodle coding
  style.
- **GPL header added to the JavaScript module.** `amd/src/player_boot.js` now
  carries the standard Moodle GPL boilerplate header like every other source file.

### Requirements
- Now requires `local_fastpix` and `mod_fastpix` 2026061500 or newer (the
  versions that serve the player/HLS libraries locally).

## [1.0.2] — 2026-06-14

### Changed
- **Permission-denied views are recorded as a standard Moodle event, not a raw
  server-log line.** When a viewer without permission opens content containing
  FastPix embeds, the filter now triggers a `capability_denied` event once per
  page render (carrying the number of embeds denied) instead of writing to the
  server error log per embed. This is visible in Moodle's standard log/report
  tools and removes the previously flagged raw `error_log()` call. The
  user-facing behaviour is unchanged — each shortcode still shows as escaped
  plain text and no video plays.

### Added
- **Plugin name shown on the admin plugin-overview page.** Added the
  `pluginname` language string so the plugin lists with a friendly name
  alongside its filter name.
- **`.gitattributes` for tidier release zips.** Repository/CI tooling
  (`.github`, `.gitignore`, `.gitattributes`) is now excluded from the published
  plugin archive; plugin code, language strings, templates, AMD build output and
  tests are still shipped.

## [1.0.0] — 2026-06-04

First release. A text filter that turns a short code into a playable FastPix
video anywhere Moodle lets you type rich text.

### Added
- **Embed videos with a short code.** Type `{fastpix:pb_<playback_id>}` in a
  forum post, Page, Book, assignment description, quiz question, label, or
  course summary, and it becomes a working video player.
- **Embed any public video, from any workspace.** You can embed a public video
  by its playback id even if it isn't in this site's FastPix library — it can
  live in any FastPix org or workspace. Public playback needs no token, so the
  filter renders a tokenless player straight from the id and the browser plays
  the stream. The server still makes no FastPix network call.
- **A clean, branded player.** Videos in this site's library play with your
  accent colour and the video title, matching the look of the FastPix activity.
- **Helpful messages instead of broken players.** For a video in this site's
  library that can't be embedded, the viewer sees a clear note explaining why:
  - a known public video still processing → *"This video is unavailable."*
  - DRM-protected → *"…cannot be played in an embed. Open it in its FastPix
    activity to watch."*
  - private → *"This video is private and can only be watched in its FastPix
    activity…"*
- **Fast page loads.** The video player loads in the background, so pages with
  embeds open quickly.
- **No personal data stored.** The filter keeps nothing about who watched what.
- **Tested.** Comes with automated tests covering the main cases.

### Security
- **Only people allowed to view the video can see it.** Anyone without
  permission just sees the plain short code text — never the video.
- **Only public videos are embedded.** Private and DRM videos are never shown
  here; a known private/DRM video points the viewer to the FastPix activity, and
  a foreign private/DRM id simply fails to play in the browser. No playback token
  is ever minted for a video the filter can't verify, so nothing leaks.
- **No watch tracking.** Embeds are for casual viewing — nothing is recorded
  and nothing is sent to the gradebook.

### Requirements
- Moodle 4.5 LTS or newer.
- The `local_fastpix` and `mod_fastpix` plugins installed.

### Good to know
- Embeds play **public videos only** — but a public video can come from any
  FastPix org or workspace, it doesn't have to be in this site's library. For
  DRM, private playback, watch tracking, or grading, use the **FastPix
  activity** instead.
- Because the filter can't tell a foreign public id from a wrong or deleted one
  without contacting FastPix, a bad id now renders a player that fails to load
  rather than the *"This video is unavailable."* message. Double-check the id if
  a player doesn't play.

[1.0.0]: https://github.com/FastPix/moodle-filter_fastpix/releases/tag/v1.0.0
