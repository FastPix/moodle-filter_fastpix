# Changelog

All notable changes to `filter_fastpix` are documented here. The format is
based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this
project follows [Semantic Versioning](https://semver.org/).

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
