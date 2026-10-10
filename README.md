# VR avatar — your own avatar for VR room live rooms

VR avatar (`local_avatar`) lets each user design the avatar that stands for them in the live rooms
of [VR room](https://github.com/adamjenkins/moodle-mod_vrroom) activities. A user designs it once,
on their own **My VR avatar** page, and the same avatar follows them into every live room on the
site, in all their courses.

The avatar is put together from ready-made parts. Nothing is uploaded, there is no free text, so
there is nothing to moderate:

| Part | Choices |
|---|---|
| Body | capsule · slim · broad |
| Head | round · rounded square |
| Colours | body colour and accent colour from 12 named colours |
| Hair | none · short · long · bun, in 6 colours |
| Extras | glasses, hat (none · cap · beanie · sun hat) |
| Face | the user's Moodle profile picture as a round badge on the face (shown by default; each user can untick it) |

The page shows a 3D preview that turns slowly (drag it, or use the arrow keys, to turn it), the
choices as ordinary labelled radio buttons and colour swatches (every colour is named, for screen
readers), **Save**, **Reset to default** and **Stop using my avatar** (back to the standard look).
The preview is drawn by VR room's own avatar builder, so what a user designs is exactly what the
others see. Where a browser has a camera, *Try my camera on the preview* shows the user's camera on
the face of the preview, as others see it in a live room when the camera is on; that video stays
in the browser.

Without WebGL the page shows the same form with a short note instead of the preview, and the
choices are saved and used all the same. Without JavaScript the form still saves.

## Requirements

- Moodle 5.2 or 5.3 (`$plugin->supported = [502, 503]`).
- VR room (`mod_vrroom`) 2026101000 or later: it provides the `avatar_appearance` hook, the
  bundled three.js and the avatar builder this plugin uses.
- Avatars appear in VR room's **built-in live mode**. The companion run mode keeps its standard
  avatars for now.

## Installation

1. Copy this repository to `public/local/avatar` in your Moodle code (Moodle 5.x keeps plugins
   under `public/`).
2. Visit *Site administration > Notifications* to install it.
3. Optionally review *Site administration > Plugins > Local plugins > VR avatar*.

Users find **My VR avatar** on their own profile page (*Miscellaneous*), or at
`/local/avatar/index.php`. A site that wants it in the user menu can add
`My VR avatar|/local/avatar/index.php` to *Appearance > Navigation > User menu items*.

## Settings

| Setting | Default | What it does |
|---|---|---|
| Profile picture on avatars (`local_avatar/allowpicture`) | on | Lets users show their profile picture on their avatar. When off, nobody's picture is sent to live rooms; the users' own choice is kept for when it is turned on again. |

Capability `local/avatar:edit` (user context, allowed for the authenticated user role): design your
own avatar. Nobody can edit another person's avatar.

## How it works

The design is stored as one user preference, `local_avatar_design`: a small JSON document
(`vrroom-avatar/1`, at most 2 KB) with only the allowed keys and names above. It is cleaned
against that allowlist when it is saved and again whenever it is read: unknown keys and values
are dropped. It is saved through the plugin's own web service, `local_avatar_save_design`, which
saves only for the current user (`local_avatar_get_design` reads it back).

When VR room builds the list of the people in one live room it dispatches the
`\mod_vrroom\hook\avatar_appearance` hook. This plugin listens and fills in the saved design of each
person in the list, and the address of their normal Moodle profile picture when the site allows it,
the user chose it and the user has a picture (an uploaded one, or a Gravatar where the site uses
them). So a person's design reaches only the people in the same live room, next to the name VR room
already shows them.

## Without this plugin

VR room's live rooms work exactly the same without it: everyone has the standard look, a simple
figure in a colour worked out from their anonymous id in the room. A user who has not saved a
design, or who chose *Stop using my avatar*, also has the standard look. Webcams in live rooms are
part of VR room itself and work with or without this plugin; this plugin only changes how the
avatar around the video looks.

## Privacy

- Stored: the user preference `local_avatar_design` (the design).
- Shared: the other participants of a live room receive the design and, if chosen and allowed, the
  address of the profile picture, while they are in the same room. Nothing is sent outside the
  site by this plugin.
- The privacy provider describes this, exports the design as a user preference and deletes it
  with the user's data in their user context.

## Licence

GNU GPL v3 or later; see `LICENSE`.

© 2026 Adam Jenkins <adam@wisecat.net>
