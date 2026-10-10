# VR avatar — your own avatar for VR room live rooms

VR avatar (`local_vravatar`) lets each user design the avatar that stands for them in the live rooms
of [VR room](https://github.com/adamjenkins/moodle-mod_vrroom) activities. A user designs it once,
on their own **My VR avatar** page, and the same avatar follows them into every live room on the
site, in all their courses.

The avatar is put together from ready-made parts. Nothing is uploaded, there is no free text, so
there is nothing to moderate:

| Part | Choices |
|---|---|
| Head | round · rounded square · oval · heart-shaped |
| Skin tone | same as body colour (the standard look) · porcelain · fair · light · medium · tan · brown · dark · deep |
| Face | eyes (dots · round · almond · sleepy), eyebrows (none · thin · thick · arched), nose (none · small · round · pointed), ears (on/off) |
| Beard | none · stubble · moustache · goatee · full beard, in the hair colour |
| Hair | none · short · long · bun · curly · afro · ponytail · braids · buzz cut · bob · mohawk · side parting · pigtails · top knot |
| Hair colour | black · brown · blonde · auburn · grey · white · red · pink · blue · purple |
| Body | capsule · slim · broad |
| Colours | body colour and accent colour from 14 named colours (black and white among them, for a black suit with a white or black tie) |
| Costume | none, or one of 44 ready-made costumes (pirates, suits, plants, aliens, robots, computers and simple characters; see [Costumes and credits](#costumes-and-credits)) |
| Extras | glasses, hat (none · cap · beanie · sun hat · pirate hat) |
| Picture | the user's Moodle profile picture as a round badge on the face (shown by default; each user can untick it) |

The heads are stylised to match the bodies, not photographs. Every new part defaults to the look
avatars had before it existed, so a design saved earlier looks the same. A costume (key `outfit`)
takes the place of the body; a computer, a television or the screen-face robot takes the place of
the head too and shows the face (picture or camera) on its screen. Some costumes (the pirate
captain's coat, the suits and ties) take the accent colour.

The page shows a 3D preview that turns slowly (drag it, or use the arrow keys, to turn it), the
choices under five headings (Head and face, Hair, Body, Costume, Extras) as ordinary labelled radio
buttons, checkboxes and colour swatches (every colour and skin tone is named,
for screen readers), the costumes as a wrapping grid of named thumbnails by category, **Save**, **Reset to default** and **Stop using my avatar** (back to the standard look). The
preview shows the head and shoulders (as wide as a costume's shoulders), or the whole laptop,
monitor, TV or computer for a device costume; *Show the whole avatar* fits everything that is
drawn, wide sleeves too. It frames again once a costume model has loaded.
The preview is drawn by VR room's own avatar builder, so what a user designs is exactly what the
others see. Where a browser has a camera, *Try my camera on the preview* shows the user's camera on
the face of the preview, as others see it in a live room when the camera is on; that video stays
in the browser.

Without WebGL the page shows the same form with a short note instead of the preview, and the
choices are saved and used all the same. Without JavaScript the form still saves.

## Requirements

- Moodle 5.2 or 5.3 (`$plugin->supported = [502, 503]`).
- VR room (`mod_vrroom`) 2026101001 or later: it provides the `avatar_appearance` hook (with the
  costume model addresses), the bundled three.js and the avatar builder this plugin uses.
- Avatars appear in VR room's **built-in live mode**. The companion run mode keeps its standard
  avatars for now.

## Installation

1. Copy this repository to `public/local/vravatar` in your Moodle code (Moodle 5.x keeps plugins
   under `public/`).
2. Visit *Site administration > Notifications* to install it.
3. Optionally review *Site administration > Plugins > Local plugins > VR avatar*.

Users find **My VR avatar** on their own profile page (*Miscellaneous*), or at
`/local/vravatar/index.php`. A site that wants it in the user menu can add
`My VR avatar|/local/vravatar/index.php` to *Appearance > Navigation > User menu items*.

## Settings

| Setting | Default | What it does |
|---|---|---|
| Profile picture on avatars (`local_vravatar/allowpicture`) | on | Lets users show their profile picture on their avatar. When off, nobody's picture is sent to live rooms; the users' own choice is kept for when it is turned on again. |

Capability `local/vravatar:edit` (user context, allowed for the authenticated user role): design your
own avatar. Nobody can edit another person's avatar.

## How it works

The design is stored as one user preference, `local_vravatar_design`: a small JSON document
(`vrroom-avatar/1`, at most 2 KB) with only the allowed keys and names above. It is cleaned
against that allowlist when it is saved and again whenever it is read: unknown keys and values
are dropped. It is saved through the plugin's own web service, `local_vravatar_save_design`, which
saves only for the current user (`local_vravatar_get_design` reads it back).

When VR room builds the list of the people in one live room it dispatches the
`\mod_vrroom\hook\avatar_appearance` hook. This plugin listens and fills in the saved design of each
person in the list, and the address of their normal Moodle profile picture when the site allows it,
the user chose it and the user has a picture (an uploaded one, or a Gravatar where the site uses
them). So a person's design reaches only the people in the same live room, next to the name VR room
already shows them. A design with a costume, or the pirate hat, also gives the addresses of those
models (`/local/vravatar/costumes/<id>.glb`), which the avatar builder loads.

The costume models are static files of this plugin: `costumes/<id>.glb`, listed in
`costumes/costumes.json` (id, kind, category, credit, licence, source, whether it takes the accent
colour, thumbnail), with thumbnails in `pix/costumes/<id>.png`. The web server serves them like the
plugin's other static files (with Moodle 5.x's `public/` layout the plugin is at
`public/local/vravatar`, the site's document root, so the address is `<wwwroot>/local/vravatar/costumes/<id>.glb`).
A server that refuses unknown file types should serve `.glb` as `model/gltf-binary` (or
`application/octet-stream`, as nginx and Apache do by default).

## Without this plugin

VR room's live rooms work exactly the same without it: everyone has the standard look, a simple
figure in a colour worked out from their anonymous id in the room. A user who has not saved a
design, or who chose *Stop using my avatar*, also has the standard look. Webcams in live rooms are
part of VR room itself and work with or without this plugin; this plugin only changes how the
avatar around the video looks.

## Privacy

- Stored: the user preference `local_vravatar_design` (the design).
- Shared: the other participants of a live room receive the design and, if chosen and allowed, the
  address of the profile picture, while they are in the same room. Nothing is sent outside the
  site by this plugin.
- The privacy provider describes this, exports the design as a user preference and deletes it
  with the user's data in their user context.

## Costumes and credits

The costume models are other authors' work, adapted for avatars (the head removed, or the screen
marked as the face screen; turned to face forward, scaled to the avatar, textures made smaller).
They are CC0 (public domain) except three that are CC BY 3.0, whose authors must be credited: the
designer shows the credit under each of them and lists the credits of every model in *Credits for
the costume models*. Each model is also listed in `thirdpartylibs.xml`. The thumbnails are rendered
from the models and carry the same licence.

The CC BY 3.0 legal code is not copied into this plugin; it is at
<https://creativecommons.org/licenses/by/3.0/legalcode>. CC0 1.0:
<https://creativecommons.org/publicdomain/zero/1.0/legalcode>.

| Costume | Id | Kind | Model | Author | Licence |
|---|---|---|---|---|---|
| Pirate captain (Pirates) | `pirate-captain` | body | [Pretty generic Pirate Captain](https://poly.pizza/m/CwWl7iqnxN) | AliceCassie | CC0-1.0 |
| Pirate (Pirates) | `pirate-woman` | body | [Pirate Woman](https://poly.pizza/m/qRDrzfs8j9) | Polygonal Mind | CC0-1.0 |
| Pirate hat (Pirates) | `hat-pirate` | accessory | [Pirate hat](https://poly.pizza/m/9QEds6cYAy6) | Poly by Google | CC-BY-3.0 |
| Business suit (Suits) | `business-dude` | body | [Business Dude](https://poly.pizza/m/odKDM2t8Cr) | Polygonal Mind | CC0-1.0 |
| Suit and tie (Suits) | `suit-tie` | body | [Suit & Tie Guy](https://poly.pizza/m/cgdjE3cst7f) | Ian MacGillivray | CC-BY-3.0 |
| Sunflower (Plants) | `sunflower` | body | [Sun Flower Person](https://poly.pizza/m/5MGoojA1LB) | Polygonal Mind | CC0-1.0 |
| Cactus (Plants) | `cactus` | body | [Cactus Character](https://poly.pizza/m/8XdFnQQsBK) | Polygonal Mind | CC0-1.0 |
| Green alien (Aliens) | `alien-green` | body | [Alien](https://poly.pizza/m/roXp0kV2X4) | Polygonal Mind | CC0-1.0 |
| Pink alien (Aliens) | `alien-pink` | body | [Alien](https://poly.pizza/m/a5T5a6j6tl) | Polygonal Mind | CC0-1.0 |
| Screen-face robot (Robots) | `robot-screen` | device | [Robot](https://poly.pizza/m/ejDr8lRglP) | Polygonal Mind | CC0-1.0 |
| Robot with lights (Robots) | `lalobot` | body | [Lalobot](https://poly.pizza/m/tKdNH5jzNt) | Polygonal Mind | CC0-1.0 |
| Laptop (Computers) | `device-laptop` | device | [Laptop](https://poly.pizza/m/GnbwSUiVty) | Kenney | CC0-1.0 |
| Computer screen (Computers) | `device-monitor` | device | [Computer Screen](https://poly.pizza/m/V5Qo141OcB) | Kenney | CC0-1.0 |
| Old television (Computers) | `device-tv` | device | [Television Vintage](https://poly.pizza/m/aSmz6H8aeu) | Kenney | CC0-1.0 |
| Desktop computer (Computers) | `device-computer` | device | [Simple computer](https://poly.pizza/m/doMMnviJrGi) | Robert Schlyter | CC-BY-3.0 |
| Blocky A (Characters) | `blocky-a` | body | [Blocky Characters (character-a)](https://kenney.nl/assets/blocky-characters) | Kenney | CC0-1.0 |
| Blocky B (Characters) | `blocky-b` | body | [Blocky Characters (character-b)](https://kenney.nl/assets/blocky-characters) | Kenney | CC0-1.0 |
| Blocky C (Characters) | `blocky-c` | body | [Blocky Characters (character-c)](https://kenney.nl/assets/blocky-characters) | Kenney | CC0-1.0 |
| Blocky D (Characters) | `blocky-d` | body | [Blocky Characters (character-d)](https://kenney.nl/assets/blocky-characters) | Kenney | CC0-1.0 |
| Blocky E (Characters) | `blocky-e` | body | [Blocky Characters (character-e)](https://kenney.nl/assets/blocky-characters) | Kenney | CC0-1.0 |
| Blocky F (Characters) | `blocky-f` | body | [Blocky Characters (character-f)](https://kenney.nl/assets/blocky-characters) | Kenney | CC0-1.0 |
| Blocky G (Characters) | `blocky-g` | body | [Blocky Characters (character-g)](https://kenney.nl/assets/blocky-characters) | Kenney | CC0-1.0 |
| Blocky H (Characters) | `blocky-h` | body | [Blocky Characters (character-h)](https://kenney.nl/assets/blocky-characters) | Kenney | CC0-1.0 |
| Blocky I (Characters) | `blocky-i` | body | [Blocky Characters (character-i)](https://kenney.nl/assets/blocky-characters) | Kenney | CC0-1.0 |
| Blocky J (Characters) | `blocky-j` | body | [Blocky Characters (character-j)](https://kenney.nl/assets/blocky-characters) | Kenney | CC0-1.0 |
| Blocky K (Characters) | `blocky-k` | body | [Blocky Characters (character-k)](https://kenney.nl/assets/blocky-characters) | Kenney | CC0-1.0 |
| Blocky L (Characters) | `blocky-l` | body | [Blocky Characters (character-l)](https://kenney.nl/assets/blocky-characters) | Kenney | CC0-1.0 |
| Blocky M (Characters) | `blocky-m` | body | [Blocky Characters (character-m)](https://kenney.nl/assets/blocky-characters) | Kenney | CC0-1.0 |
| Blocky N (Characters) | `blocky-n` | body | [Blocky Characters (character-n)](https://kenney.nl/assets/blocky-characters) | Kenney | CC0-1.0 |
| Blocky O (Characters) | `blocky-o` | body | [Blocky Characters (character-o)](https://kenney.nl/assets/blocky-characters) | Kenney | CC0-1.0 |
| Blocky P (Characters) | `blocky-p` | body | [Blocky Characters (character-p)](https://kenney.nl/assets/blocky-characters) | Kenney | CC0-1.0 |
| Blocky Q (Characters) | `blocky-q` | body | [Blocky Characters (character-q)](https://kenney.nl/assets/blocky-characters) | Kenney | CC0-1.0 |
| Blocky R (Characters) | `blocky-r` | body | [Blocky Characters (character-r)](https://kenney.nl/assets/blocky-characters) | Kenney | CC0-1.0 |
| Mini man A (Characters) | `mini-male-a` | body | [Mini Characters (character-male-a)](https://kenney.nl/assets/mini-characters) | Kenney | CC0-1.0 |
| Mini man B (Characters) | `mini-male-b` | body | [Mini Characters (character-male-b)](https://kenney.nl/assets/mini-characters) | Kenney | CC0-1.0 |
| Mini man C (Characters) | `mini-male-c` | body | [Mini Characters (character-male-c)](https://kenney.nl/assets/mini-characters) | Kenney | CC0-1.0 |
| Mini man D (Characters) | `mini-male-d` | body | [Mini Characters (character-male-d)](https://kenney.nl/assets/mini-characters) | Kenney | CC0-1.0 |
| Mini man E (Characters) | `mini-male-e` | body | [Mini Characters (character-male-e)](https://kenney.nl/assets/mini-characters) | Kenney | CC0-1.0 |
| Mini man F (Characters) | `mini-male-f` | body | [Mini Characters (character-male-f)](https://kenney.nl/assets/mini-characters) | Kenney | CC0-1.0 |
| Mini woman A (Characters) | `mini-female-a` | body | [Mini Characters (character-female-a)](https://kenney.nl/assets/mini-characters) | Kenney | CC0-1.0 |
| Mini woman B (Characters) | `mini-female-b` | body | [Mini Characters (character-female-b)](https://kenney.nl/assets/mini-characters) | Kenney | CC0-1.0 |
| Mini woman C (Characters) | `mini-female-c` | body | [Mini Characters (character-female-c)](https://kenney.nl/assets/mini-characters) | Kenney | CC0-1.0 |
| Mini woman D (Characters) | `mini-female-d` | body | [Mini Characters (character-female-d)](https://kenney.nl/assets/mini-characters) | Kenney | CC0-1.0 |
| Mini woman E (Characters) | `mini-female-e` | body | [Mini Characters (character-female-e)](https://kenney.nl/assets/mini-characters) | Kenney | CC0-1.0 |
| Mini woman F (Characters) | `mini-female-f` | body | [Mini Characters (character-female-f)](https://kenney.nl/assets/mini-characters) | Kenney | CC0-1.0 |

The Blocky and Mini characters are from Kenney's packs [Blocky Characters](https://kenney.nl/assets/blocky-characters)
and [Mini Characters](https://kenney.nl/assets/mini-characters) (CC0).

## Licence

GNU GPL v3 or later; see `LICENSE`. The costume models: see [Costumes and credits](#costumes-and-credits).

© 2026 Adam Jenkins <adam@wisecat.net>
