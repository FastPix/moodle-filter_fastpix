// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

// Player boot AMD entry point for filter_fastpix.
//
// Attached by filter.php to any page that rendered at least one
// {fastpix:pb_<id>} embed. Mounts the <fastpix-player> web component into every
// wrapper the filter emitted. Mirrors mod_fastpix/player's mount path, stripped
// of all watch-tracking (no coverage tracker, no session token, no completion
// UI) — embeds are casual viewing (invariants 5, 6).
//
// Unlike the activity player, which receives a single payload via js_call_amd,
// the filter can emit MANY embeds in one text block, so each wrapper carries its
// own data-* attributes and we mount them all. The ESM library URLs (the only
// per-invocation config) arrive in init()'s argument.
//
// DRM token handling is deferred to the next build phase (defer-drm-token): the
// rendered data-drm-token is empty and is filled on first play there. For now a
// non-DRM embed mounts and plays; a DRM embed mounts without a token.

// Native dynamic import(), hidden from Babel behind the Function constructor so
// the build cannot rewrite it into a RequireJS require([...]). Mirrors the
// esmImport helper in mod_fastpix/amd/src/player.js. Keep CDN/ESM loads going
// through here, never a bare literal import(). The Function constructor is the
// only way to preserve native import() through the AMD build, so the no-new-func
// rule is deliberately disabled for this one line.
// eslint-disable-next-line no-new-func
const esmImport = (url) => Function('u', 'return import(u);')(url);

/**
 * Mount a single <fastpix-player> into a wrapper from its data-* attributes.
 *
 * @param {HTMLElement} wrapperEl The [data-region="fastpix-player-wrapper"] node.
 * @param {Object} config The per-invocation config: {playerliburl, hlsliburl}.
 * @return {Promise<void>}
 */
const mount = async(wrapperEl, config) => {
    if (!wrapperEl || !config) {
        return;
    }
    // Idempotency — never mount twice into the same wrapper.
    if (wrapperEl.querySelector('fastpix-player')) {
        return;
    }

    try {
        if (!window.Hls) {
            const hlsMod = await esmImport(config.hlsliburl);
            window.Hls = hlsMod.default || hlsMod.Hls || hlsMod;
        }
        if (!window.customElements.get('fastpix-player')) {
            await esmImport(config.playerliburl);
        }
    } catch (err) {
        if (window.console) {
            window.console.error('[filter_fastpix] player load failed', err);
        }
        return;
    }
    // Re-check after async deps load — a concurrent mount may have won.
    if (wrapperEl.querySelector('fastpix-player')) {
        return;
    }

    const data = wrapperEl.dataset;
    const el = document.createElement('fastpix-player');
    el.setAttribute('playback-id', data.playbackId || '');
    if (data.playbackToken) {
        el.setAttribute('token', data.playbackToken);
    }
    if (data.drmToken) {
        // Empty at first render; the deferred-token phase fills it on play.
        el.setAttribute('drm-token', data.drmToken);
    }
    el.setAttribute('stream-type', 'on-demand');
    if (data.accentColor) {
        el.setAttribute('accent-color', data.accentColor);
    }
    if (data.videoTitle) {
        el.setAttribute('metadata-video-title', data.videoTitle);
    }
    // Sizing lives in styles.css (.filter_fastpix-player-wrapper fastpix-player)
    // — no inline element styling (Moodle forbids inline CSS).
    wrapperEl.appendChild(el);
};

/**
 * Initialise every FastPix embed on the page.
 *
 * @param {Object} config The per-invocation config: {playerliburl, hlsliburl}.
 * @return {void}
 */
export const init = (config) => {
    const wrappers = document.querySelectorAll('[data-region="fastpix-player-wrapper"]');
    wrappers.forEach((wrapperEl) => {
        mount(wrapperEl, config);
    });
};
