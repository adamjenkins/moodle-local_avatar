# Changelog

All notable changes to this plugin are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [0.1.0] - Unreleased

ALPHA. First release, Moodle 5.2 and 5.3, requires mod_vrroom 2026101001. Not yet tagged.

### Added

- `index.php` "My VR avatar" designer (template `local_avatar/designer`, AMD `local_avatar/designer`
  drawing the preview with `vrroomruntime_builtin/live/avatarmodel`), linked through
  `local_avatar_myprofile_navigation()`.
- `\local_avatar\local\design`: the `vrroom-avatar/1` allowlist (`clean()`, 2 KB cap), stored in the
  user preference `local_avatar_design`.
- Web services `local_avatar_save_design` and `local_avatar_get_design` (current user only,
  capability `local/avatar:edit`).
- Listener of `\mod_vrroom\hook\avatar_appearance` filling each live-room peer's design and profile
  picture URL.
- Setting `local_avatar/allowpicture` (default on).
- Privacy provider (user preference, live-room participants as an external location).
- PHPUnit tests, Behat feature (`@local_avatar`; the WebGL preview scenario is `@local_avatar_webgl`)
  and a GitHub Actions workflow.
- Hair and heads in `vrroom-avatar/1` (all optional, each defaulting to the earlier look): `hair`
  14 styles, `haircolor` 10 colours, `head` round/square/oval/heart, new `skin` (match + 8 tones),
  `eyes`, `brows`, `nose`, `ears` (flag), `beard` (in the hair colour), and the reserved `outfit`
  (only `none`). The designer page shows them under four headings; `local_avatar_save_design` and
  `local_avatar_get_design` return the new keys.
- Costumes: `costumes/<id>.glb` (45 models: 44 outfits of kind body or device, and the accessory
  `hat-pirate`), the manifest `costumes/costumes.json`, thumbnails `pix/costumes/<id>.png`,
  `\local_avatar\local\costumes`, credits in `thirdpartylibs.xml` and the README. `outfit` takes
  `none` or a manifest id of kind body/device; `hat` gains `pirate`. The hook listener passes the
  models' URLs as `set_appearance()`'s `$assets` (`outfit`, `hat`); the designer has a Costume
  section (thumbnail radio grid by category, CC BY credits, a credits list) and passes the assets to
  the avatar builder. `outfit` is `PARAM_ALPHANUMEXT` in the web service structure and the no-JS
  form (costume ids have hyphens).
- Colours: `black` and `white` added to the body and accent colours (14 in all). The suits keep a
  black suit and white shirt and tint only the tie with the accent colour. The aliens have long
  fingers, the Kenney Mini characters stand straight, and the robot's lamps are larger and glow.
- Designer preview: frames only the visible parts (the hidden head no longer crops device
  costumes), fits wide costumes in the whole view, and reframes once a costume has loaded.
- Designer: sticky preview offset by Boost's `--navbar-height`; the face-view framing includes the
  name label; the zoom button is a toggle with `aria-pressed` (string `showface` removed).
- `$plugin->version` 2026101001 (new strings, templates and the mod_vrroom dependency).
