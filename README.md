# filter_fastpix - embed FastPix videos anywhere in Moodle with a short code

[![Moodle 4.5+](https://img.shields.io/badge/Moodle-4.5%2B-F98012?logo=moodle&logoColor=white)](https://moodle.org/)
[![PHP 8.1+](https://img.shields.io/badge/PHP-8.1%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Release 1.0.3](https://img.shields.io/badge/release-1.0.3-0E7C66)](https://github.com/FastPix/moodle-filter_fastpix/blob/main/CHANGELOG.md)

A Moodle text filter that turns a short code into a playable
[FastPix](https://www.fastpix.com)-hosted video **anywhere Moodle shows
rich text** - forum posts, Pages, Book chapters, labels, course and
section summaries, quiz question text and feedback, and more. No
activity required. It builds on `local_fastpix`, the foundation plugin
that provides the FastPix credentials, HTTP gateway, and playback-token
signing.

Use this plugin if you run a Moodle site with `local_fastpix` already
connected and you want authors to embed FastPix videos directly inside
their content. `filter_fastpix` also requires `mod_fastpix`, the FastPix
Video activity plugin, because the filter reuses its view capability
and player; install all three. `filter_fastpix` never contacts FastPix
directly; every video operation goes through `local_fastpix`. It stores
nothing, tracks nothing, and grades nothing - it's a pure display layer
for casual viewing. For graded, completion-tracked videos, use the
**FastPix Video** activity (`mod_fastpix`) instead.

**Works with:** Moodle 4.5 LTS or later · PHP 8.1+ · requires `local_fastpix` + `mod_fastpix` · public FastPix videos (embedded by playback ID)

📖 **Filter guide:** https://fastpix.com/docs/moodle/filter-plugin &nbsp;·&nbsp; 🚀 **Free FastPix account:** https://dashboard.fastpix.com/signup

> **Part of the FastPix Moodle suite.** Install `local_fastpix` and `mod_fastpix` first, then **enable this filter** (see [Enable the filter](#enable-the-filter)). This filter is for casual embedding; for graded, completion-tracked videos use the FastPix Video activity (`mod_fastpix`). See [The FastPix Moodle suite](#the-fastpix-moodle-suite).


## Features

### Embedding

- Drop a short code into any rich-text field and it becomes a video
  player when the page is viewed.
- Works no matter how the short code got there: inserted by the FastPix
  editor button (`tiny_fastpix`), typed by hand, or carried in from
  migrated content.
- Put as many short codes as you like on one page; each becomes its own
  player, and the surrounding text is left untouched.

### Playback experience

- Plays **public** FastPix videos in the same modern adaptive player the
  FastPix activity uses - across browsers, mobile, and slow networks.
- Embed **any public video by its playback id**, from any FastPix org or
  workspace - it needn't be in this site's library.
- Library videos also show your accent color and the video title.
- Fast: no short code means no work, and rendering makes no FastPix
  network calls.
- Private/DRM videos show a "watch it in the activity" note; an
  unplayable id just fails to load, leaking nothing.

### Security and privacy

- Every embed is checked against the `mod/fastpix:view` capability
  **in the place the text appears**. Viewers without it never see the
  video - they just see the harmless short-code text, so videos can't
  leak.
- No watch tracking, no grades, no completion, and no personal data
  stored.

## Requirements

- Moodle 4.5 LTS or later.
- PHP 8.1 or later (tested through PHP 8.3).
- `local_fastpix` 1.0.0 or later, installed and connected to a FastPix
  account.
  [Set up the foundation plugin](https://fastpix.com/docs/moodle/local-plugin)
  first if you haven't.
- `mod_fastpix` installed - this filter reuses its `mod/fastpix:view`
  capability and mirrors its player.

This filter adds no database tables, has no FastPix credentials of its
own, and has no Composer dependencies.

## Install

Choose one of the following methods.

### Install from the Moodle Marketplace

1. Sign in to your Moodle site as an administrator.
2. Go to **Site administration > Plugins > Install plugins**.
3. Search for **FastPix video embeds** and follow the prompts.

### Install from a ZIP file

1. Download the latest release from the Moodle Marketplace page,
   or from the GitHub Releases page.
2. Sign in to your Moodle site as an administrator.
3. Go to **Site administration > Plugins > Install plugins** and
   upload the ZIP file. Don't unzip it first; Moodle installs the
   package directly from the ZIP.
4. Select **Install plugin from the ZIP file**, then continue through
   the validation screen.
5. On the **Plugins requiring attention** screen, select **Upgrade
   Moodle database now**, then **Continue** when it finishes.

> **Note:** You can't install `filter_fastpix` standalone. Moodle
> blocks the install with a dependency error until `local_fastpix` and
> `mod_fastpix` are present. If you haven't set up the foundation
> plugin yet, see
> [Set up local plugin](https://fastpix.com/docs/moodle/local-plugin)
> first.

### Enable the filter

After installing, go to **Site administration > Plugins > Filters >
Manage filters** and switch **FastPix video embeds** to **On**. Place
it **above** the **Multimedia plugins** filter so FastPix handles its
own short codes first.

## Usage

Authors add videos directly from any rich-text editor. No admin action
is needed once the filter is enabled.

### The short code

```
{fastpix:pb_YOUR_PLAYBACK_ID}
```

| Part | Meaning |
|---|---|
| `{fastpix:` … `}` | Tells the filter "this is a FastPix video." |
| `pb_` | Marks what follows as a playback id. |
| `YOUR_PLAYBACK_ID` | The video's playback id (letters, numbers, `-` and `_`). |

Example: `{fastpix:pb_a1c229cb-fb6f-41b1-b4cd-d676585077d5}`

### Add a video

You have two ways to add a video:

- **Editor button (recommended).** If `tiny_fastpix` is installed,
  select **Insert FastPix video** in the editor toolbar, pick a video
  from the list (shown by name), and the correct short code is
  inserted for you.
- **Paste the short code.** Type or paste
  `{fastpix:pb_YOUR_PLAYBACK_ID}` into any rich-text field and save. On
  view, readers see the player.

You can mix short codes freely with normal text and place several on
one page.

### What each viewer sees

| Situation | What they see |
|---|---|
| Has `mod/fastpix:view` and the video is **public** | The video player. |
| Public video from another org or workspace (not in this site's library) | The video player, rendered tokenless straight from the playback id. |
| No permission | The plain short-code text - no video, no error. |
| Video is private (non-DRM), **in this site's library** | A note to watch it in its FastPix activity. |
| Video is DRM-protected, **in this site's library** | A note to open it in its FastPix activity to watch. |
| A known public video still processing | "This video is unavailable." |
| A wrong, deleted, or private id **not** in this site's library | The player mounts but can't play (the stream won't authorise). No token is minted, so nothing leaks. |

## Limitations

- **Public videos only - but from any workspace.** You can embed any
  public video by its playback id, regardless of which FastPix org or
  workspace it lives in. Private and DRM videos aren't embedded; a known
  one points the viewer to the FastPix activity, which owns the per-play
  token-refresh machinery they need.
- **No tracking, grades, or completion.** Watching an embedded video
  records nothing. Use the FastPix activity (`mod_fastpix`) when you
  need those.
- **No uploading.** This plugin only displays videos that already exist
  in FastPix; uploading is done through the activity or the editor
  button.

## Capabilities

This filter doesn't define its own capabilities. It reuses the
activity's view capability so that embed access matches activity
access.

| Capability | Description | Defined by |
|---|---|---|
| `mod/fastpix:view` | View and play a FastPix video. Checked per short code, in the context where the text appears. | `mod_fastpix` |

## The FastPix Moodle suite

`filter_fastpix` renders the short code into a player. It needs the foundation and activity plugins installed first, and pairs with the editor button. All are GPL-3.0; install in this order:

| Plugin | Role | Install order |
|---|---|---|
| [local_fastpix](https://github.com/FastPix/moodle-local_fastpix) | Foundation: FastPix credentials, secure gateway, playback-token signing. **Required first.** | 1 |
| [mod_fastpix](https://github.com/FastPix/moodle-mod_fastpix) | The **FastPix Video** activity - upload, graded, completion-tracked videos; defines the `mod/fastpix:view` capability this filter reuses. | 2 |
| **filter_fastpix** (this repo) | Turns a `{fastpix:pb_...}` short code into a player anywhere Moodle renders rich text. **Enable it after installing.** | 3 |
| [tiny_fastpix](https://github.com/FastPix/moodle-tiny_fastpix) | A TinyMCE editor button that inserts the short code for authors. | 4 |

Browse everything in the [FastPix organization](https://github.com/orgs/FastPix/repositories).

## FAQ

**What is filter_fastpix, and what do I need first?**
It is a Moodle text filter that turns a short code into a playable FastPix video anywhere Moodle renders rich text. It needs `local_fastpix` and `mod_fastpix` installed, and this filter enabled. See [Requirements](#requirements) and [The FastPix Moodle suite](#the-fastpix-moodle-suite).

**How do I embed a FastPix video in a page, forum post, or label?**
Put a `{fastpix:pb_YOUR_PLAYBACK_ID}` short code into any rich-text field and save; on view it becomes a player. Or insert it with the `tiny_fastpix` editor button. See [The short code](#the-short-code) and [Add a video](#add-a-video).

**Where do I get a playback ID for the short code?**
It is the playback ID of a FastPix video (visible in the FastPix dashboard media details, or on the video's FastPix activity). If you have the `tiny_fastpix` editor button installed, it inserts the correct short code for you so you never handle the ID directly. See [Add a video](#add-a-video).

**Why does my short code show as plain text instead of a video?**
The filter is not enabled, or it sits below the Multimedia plugins filter. Enable **FastPix video embeds** and place it above **Multimedia plugins**. See [Enable the filter](#enable-the-filter) and [Troubleshooting](#troubleshooting).

**Which videos can I embed?**
Public FastPix videos, by playback ID, from any FastPix org or workspace. Private and DRM videos are not embedded; a known one points the viewer to its FastPix activity. See [Limitations](#limitations).

**Does it track watching, grade, or store data?**
No. It is a display-only layer - no tracking, grades, completion, or personal data. For graded, completion-tracked videos, use the FastPix Video activity (`mod_fastpix`). See [Limitations](#limitations).

**Who can see an embedded video?**
Viewers with the `mod/fastpix:view` capability in the place the text appears; everyone else just sees the harmless short-code text, so videos cannot leak. See [Capabilities](#capabilities).

**Which Moodle and PHP versions are supported?**
Moodle 4.5 LTS or later, and PHP 8.1 or later (tested through PHP 8.3). See [Requirements](#requirements).

## Troubleshooting

- **Short code renders as plain `{fastpix:...}` text.** Enable the **FastPix video embeds** filter under **Site administration > Plugins > Filters > Manage filters**, and place it **above** the **Multimedia plugins** filter.
- **A private or DRM video shows a note instead of playing.** The filter embeds public videos only; open private or DRM videos in their FastPix activity.
- **The player mounts but the video will not play.** Check that the playback ID is a real, public, ready FastPix video.
- **Moodle refuses to install the plugin.** `local_fastpix` and `mod_fastpix` must be present first.

## Privacy

This plugin stores no personal data of its own. It declares a
`null_provider` and delegates everything to `local_fastpix`. See
`classes/privacy/provider.php`.

## Support

- File an issue on the
  [issue tracker](https://github.com/FastPix/moodle-filter_fastpix/issues).
- Read the
  [integration guide](https://fastpix.com/docs/moodle/filter-plugin)
  for installation and usage walkthroughs.
- Set up the
  [foundation plugin](https://fastpix.com/docs/moodle/local-plugin)
  if you haven't already.
- Read the
  [changelog](https://github.com/FastPix/moodle-filter_fastpix/blob/main/CHANGELOG.md)
  for release notes.

## License

Copyright © 2026 FastPix Inc. Released under the
[GNU GPL v3.0 or later](https://www.gnu.org/licenses/gpl-3.0.html).
For the full license text, see
[`LICENSE`](https://github.com/FastPix/moodle-filter_fastpix/blob/main/LICENSE).
