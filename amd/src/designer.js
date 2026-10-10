// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * The "My VR avatar" designer: a 3D preview of the avatar that follows the form, saving through
 * the local_vravatar_save_design web service, Reset to default and a local camera preview.
 *
 * The preview is drawn by VR room's own avatar builder (vrroomruntime_builtin/live/avatarmodel), the
 * same module that draws the avatars in live rooms, so what a user designs is what others see. The
 * camera preview stays in this browser: nothing is sent anywhere. A costume (or a hat that is a
 * costume model) is a glTF model of this plugin: its address is on the radio button
 * (data-model) and the builder loads it.
 *
 * @module     local_vravatar/designer
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import * as THREE from 'vrroomruntime_builtin/three';
import * as AvatarModel from 'vrroomruntime_builtin/live/avatarmodel';
import Ajax from 'core/ajax';
import Log from 'core/log';
import Notification from 'core/notification';
import Pending from 'core/pending';
import {getStrings} from 'core/str';

/** Turntable speed (radians per second): slow, so the face is seen often. */
const SPIN_SPEED = 0.25;

/** How long the preview holds the face towards the viewer after a change or at the start (ms). */
const HOLD_MS = 3000;

/** How long the turntable waits after the user turned the avatar before it turns again (ms). */
const IDLE_MS = 5000;

/** How fast the avatar turns back to face the viewer (share of the angle per second). */
const RETURN_RATE = 6;

/** Radians turned per pixel dragged. */
const DRAG_SPEED = 0.01;

/** Radians turned per arrow key press. */
const KEY_STEP = Math.PI / 12;

/** Field of view of the preview camera (degrees). */
const FOV = 30;

/** How far below the head the close-up reaches, to show the shoulders (m). */
const SHOULDERS = 0.3;

/** Widest a body costume's shoulders make the close-up, in SHOULDERS (sleeves held out stop here). */
const WIDEST = 3;

/** Room left above the head (and its hat) in the close-up (m). */
const HEADROOM = 0.04;

/** How long the preview follows the avatar's size after a change, while a costume model loads (ms). */
const SETTLE_MS = 4000;

/** Camera video size for the preview, as in live rooms. */
const VIDEO = {width: {ideal: 320}, height: {ideal: 240}, frameRate: {ideal: 15}};

/** Keys of the document that take a name (radio groups; "outfit" is the costume picker). */
const CHOICES = [
    'body', 'head', 'skin', 'color', 'accent', 'hair', 'haircolor', 'eyes', 'brows', 'nose', 'beard', 'hat', 'outfit',
];

/** The keys whose chosen radio button may name a model to load (data-model), by asset name. */
const ASSETS = {outfit: 'outfit', hat: 'hat'};

/** Keys of the document that are true or false (checkboxes). */
const FLAGS = ['ears', 'glasses', 'picture'];

/** The strings the page uses, in this order. */
const STRINGS = [
    'saving', 'saved', 'resetdone', 'camera_on', 'camera_off', 'camera_error', 'camerapreview', 'camerapreviewstop',
    'showwhole',
];

/**
 * Read the design from the form.
 *
 * @param {HTMLFormElement} form The form.
 * @param {Object} defaults The default document.
 * @returns {Object} The document.
 */
const readForm = (form, defaults) => {
    const design = Object.assign({}, defaults);
    CHOICES.forEach((key) => {
        const checked = form.querySelector(`input[type="radio"][name="${key}"]:checked`);
        if (checked) {
            design[key] = checked.value;
        }
    });
    FLAGS.forEach((key) => {
        const box = form.querySelector(`input[type="checkbox"][name="${key}"]`);
        if (box) {
            design[key] = box.checked;
        }
    });
    return design;
};

/**
 * The models the form's choices name, for the avatar builder: asset name => address.
 *
 * @param {HTMLFormElement} form The form.
 * @returns {Object} {outfit: url, hat: url}, each only when chosen.
 */
const readAssets = (form) => {
    const assets = {};
    Object.keys(ASSETS).forEach((name) => {
        const checked = form.querySelector(`input[type="radio"][name="${ASSETS[name]}"]:checked`);
        if (checked && checked.dataset.model) {
            assets[name] = checked.dataset.model;
        }
    });
    return assets;
};

/**
 * Put a design into the form.
 *
 * @param {HTMLFormElement} form The form.
 * @param {Object} design The document.
 */
const writeForm = (form, design) => {
    CHOICES.forEach((key) => {
        form.querySelectorAll(`input[type="radio"][name="${key}"]`).forEach((radio) => {
            radio.checked = radio.value === design[key];
        });
    });
    FLAGS.forEach((key) => {
        const box = form.querySelector(`input[type="checkbox"][name="${key}"]`);
        if (box) {
            box.checked = !!design[key];
        }
    });
};

/**
 * The avatar builder's palette for one kind of swatch (data-palette), when it exports one.
 *
 * @param {string} palette "colour", "hair" or "skin".
 * @returns {Object|undefined} name => colour (CSS string or number)
 */
const builderPalette = (palette) => {
    switch (palette) {
        case 'hair':
            return AvatarModel.HAIR_COLOURS;
        case 'skin':
            return AvatarModel.SKIN_TONES || AvatarModel.SKIN_COLOURS;
        case 'colour':
            return AvatarModel.COLOURS;
        default:
            return undefined;
    }
};

/**
 * Paint the swatches with the avatar builder's own colours when it lists them, so a swatch shows
 * exactly the colour of the avatar.
 *
 * @param {HTMLElement} root The designer.
 */
const paintSwatches = (root) => {
    root.querySelectorAll('[data-swatch]').forEach((swatch) => {
        const palette = builderPalette(swatch.dataset.palette);
        const colour = palette && palette[swatch.dataset.swatch];
        if (typeof colour === 'string') {
            swatch.style.backgroundColor = colour;
        } else if (typeof colour === 'number') {
            swatch.style.backgroundColor = '#' + colour.toString(16).padStart(6, '0');
        }
    });
};

/**
 * Whether this browser can draw WebGL.
 *
 * @returns {boolean}
 */
const hasWebgl = () => {
    try {
        const probe = document.createElement('canvas');
        return !!(window.WebGLRenderingContext && (probe.getContext('webgl2') || probe.getContext('webgl')));
    } catch (e) {
        return false;
    }
};

/**
 * The angle between -pi and pi that turns the same way as the given one.
 *
 * @param {number} angle Radians.
 * @returns {number} Radians.
 */
const wrapAngle = (angle) => {
    const turn = Math.PI * 2;
    let wrapped = angle % turn;
    if (wrapped > Math.PI) {
        wrapped -= turn;
    } else if (wrapped < -Math.PI) {
        wrapped += turn;
    }
    return wrapped;
};

/**
 * The 3D preview: the avatar on a turntable, facing the viewer at first and after every change,
 * shown as a head-and-shoulders close-up or whole. Drag or the arrow keys turn it.
 *
 * The avatar looks along -Z (see vrroomruntime_builtin/live/avatarmodel), so the camera stands on
 * -Z looking back at it: with the turntable at 0 the face is towards the viewer.
 *
 * @param {HTMLCanvasElement} canvas The canvas.
 * @param {Object} design The document to show first.
 * @param {Object} assets The models it names ({outfit, hat}).
 * @param {Object} config Page config.
 * @returns {Object|null} {update(design, assets), setVideo(video|null), setWhole(bool)} or null when it
 *     cannot draw.
 */
const createPreview = (canvas, design, assets, config) => {
    if (typeof AvatarModel.createAvatar !== 'function') {
        Log.warn('local_vravatar: the VR room avatar builder is missing createAvatar');
        return null;
    }
    let renderer;
    try {
        renderer = new THREE.WebGLRenderer({canvas, antialias: true, alpha: true});
    } catch (e) {
        Log.warn('local_vravatar: no WebGL for the preview: ' + e);
        return null;
    }
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    if ('outputColorSpace' in renderer && THREE.SRGBColorSpace) {
        renderer.outputColorSpace = THREE.SRGBColorSpace;
    }

    // Lights stay with the viewer (on -Z), not on the turntable, so the face is always lit when it
    // looks at the viewer. A warm, light ground colour keeps pale skin from looking grey.
    const scene = new THREE.Scene();
    scene.add(new THREE.HemisphereLight(0xffffff, 0xe6dccf, 1.1));
    const key = new THREE.DirectionalLight(0xfff8f0, 1.7);
    key.position.set(0.8, 3.4, -2.6);
    scene.add(key);
    const fill = new THREE.DirectionalLight(0xffffff, 0.6);
    fill.position.set(-2.6, 1.8, -1.2);
    scene.add(fill);
    const rim = new THREE.DirectionalLight(0xffffff, 0.45);
    rim.position.set(0.5, 2.5, 3);
    scene.add(rim);

    const camera = new THREE.PerspectiveCamera(FOV, 3 / 4, 0.05, 50);
    const turntable = new THREE.Group();
    scene.add(turntable);

    const avatar = AvatarModel.createAvatar(THREE, design, {
        name: config.name,
        pictureurl: design.picture ? config.pictureurl : '',
        seed: config.seed,
        assets,
    });
    turntable.add(avatar.group);
    // The avatar stands on the turntable's axis (its head is at x = z = 0), so it turns on the spot.
    avatar.group.position.set(0, 0, 0);

    let whole = false;

    /**
     * Point the camera at a box (in turntable coordinates) from -Z so that it fits the canvas.
     *
     * @param {number} bottom Lowest point (m).
     * @param {number} top Highest point (m).
     * @param {number} width Width (m).
     * @param {number} depth Depth (m).
     * @param {number} margin Extra room around it (1 = none).
     */
    const aim = (bottom, top, width, depth, margin) => {
        const middle = (bottom + top) / 2;
        const height = Math.max(top - bottom, width / Math.max(camera.aspect, 0.1)) * margin;
        const distance = (height / 2) / Math.tan(THREE.MathUtils.degToRad(FOV / 2)) + depth / 2;
        camera.position.set(0, middle + height * 0.04, -distance);
        camera.lookAt(0, middle, 0);
    };

    /**
     * Grow a box by the parts of an object that are drawn: a hidden part (the head a device takes
     * the place of, the body a costume takes the place of) does not count, nor anything under it.
     *
     * @param {Object} object THREE.Object3D, its world matrices up to date.
     * @param {Object} box THREE.Box3 to grow.
     * @param {Function} [keep] Called with (x, y, z) world points; only those it accepts count.
     * @returns {Object} The box.
     */
    const visibleBox = (object, box, keep) => {
        const part = new THREE.Box3();
        const point = new THREE.Vector3();
        const visit = (node) => {
            if (!node.visible) {
                return;
            }
            const geometry = node.geometry;
            if (geometry && (node.isMesh || node.isSprite || node.isLine || node.isPoints)) {
                if (keep && geometry.attributes && geometry.attributes.position && !node.isSkinnedMesh) {
                    // Only the points that are kept: the vertices themselves (costumes are small).
                    const position = geometry.attributes.position;
                    for (let i = 0; i < position.count; i++) {
                        point.fromBufferAttribute(position, i).applyMatrix4(node.matrixWorld);
                        if (keep(point.x, point.y, point.z)) {
                            box.expandByPoint(point);
                        }
                    }
                } else {
                    if (node.isSkinnedMesh && typeof node.computeBoundingBox === 'function') {
                        node.computeBoundingBox();
                        part.copy(node.boundingBox);
                    } else {
                        if (!geometry.boundingBox) {
                            geometry.computeBoundingBox();
                        }
                        part.copy(geometry.boundingBox);
                    }
                    part.applyMatrix4(node.matrixWorld);
                    if (!keep || keep((part.min.x + part.max.x) / 2, (part.min.y + part.max.y) / 2,
                        (part.min.z + part.max.z) / 2)) {
                        box.union(part);
                    }
                }
            }
            node.children.forEach(visit);
        };
        visit(object);
        return box;
    };

    // Frame the avatar with the turntable facing front: the head and shoulders, or all of it.
    // eslint-disable-next-line complexity
    const frame = () => {
        const angle = turntable.rotation.y;
        turntable.rotation.y = 0;
        turntable.updateMatrixWorld(true);
        // Looked up each time: a costume may change the parts.
        const head = avatar.group.getObjectByName('head');
        const label = avatar.group.getObjectByName('label');
        const costume = avatar.group.getObjectByName('costume');
        // A device (a laptop, a monitor, a TV, a computer) hides the head: its screen is the face.
        const device = !!(costume && head && !head.visible);
        const closeup = !whole && !!head;
        const box = new THREE.Box3();
        if (!closeup) {
            // Everything that is drawn: wide sleeves, a TV's sides and a computer's base fit too.
            visibleBox(avatar.group, box);
            if (box.isEmpty()) {
                box.setFromObject(avatar.group);
            }
        } else if (device) {
            // The device takes the place of the head: all of it, with its frame round the screen.
            visibleBox(costume, box);
            const screen = avatar.group.getObjectByName('facescreen');
            if (screen) {
                visibleBox(screen, box);
            }
        } else {
            visibleBox(head, box);
            if (box.isEmpty()) {
                box.setFromObject(head);
            }
        }
        // The close-up keeps the name tag above the head in view (a tall hair style or hat pushes
        // it up).
        if (closeup && !box.isEmpty() && label && label.visible) {
            box.union(new THREE.Box3().setFromObject(label));
        }
        // A body costume's shoulders and sleeves: as wide as the costume is at the shoulders.
        let shoulders = 0;
        if (closeup && !device && costume && costume.visible && !box.isEmpty()) {
            const low = box.min.y - SHOULDERS;
            const high = box.min.y + 0.02;
            const band = visibleBox(costume, new THREE.Box3(), (x, y) => y >= low && y <= high);
            if (!band.isEmpty()) {
                shoulders = Math.min(2 * Math.max(Math.abs(band.min.x), Math.abs(band.max.x)), SHOULDERS * WIDEST);
            }
        }
        turntable.rotation.y = angle;
        turntable.updateMatrixWorld(true);
        if (box.isEmpty()) {
            const y = AvatarModel.STANDING_HEAD_HEIGHT || 1.6;
            camera.position.set(0, y, whole ? -4 : -1.5);
            camera.lookAt(0, whole ? y / 2 : y - 0.1, 0);
            return;
        }
        const size = box.getSize(new THREE.Vector3());
        // Keep the axis in the middle of the picture: as wide as the widest side of it.
        const width = 2 * Math.max(Math.abs(box.min.x), Math.abs(box.max.x));
        if (!closeup) {
            aim(box.min.y, box.max.y, width, size.z, 1.12);
        } else if (device) {
            aim(box.min.y, box.max.y + HEADROOM, width, size.z, 1.1);
        } else {
            aim(box.min.y - SHOULDERS, box.max.y + HEADROOM, Math.max(width, SHOULDERS * 1.6, shoulders), size.z, 1.05);
        }
    };

    const resize = () => {
        const width = canvas.clientWidth || 300;
        const height = canvas.clientHeight || 400;
        renderer.setSize(width, height, false);
        camera.aspect = width / height;
        camera.updateProjectionMatrix();
        frame();
    };

    const reducedmotion = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
    const still = () => !!(reducedmotion && reducedmotion.matches);
    let dragging = null;
    let hovering = false;
    let focused = false;
    let last = performance.now();
    // Hold the face towards the viewer until then, then turn slowly.
    let holduntil = last + HOLD_MS;
    // The user turned the avatar: leave it as they put it until then.
    let touched = -Infinity;
    // Turn back to face the viewer (after a change).
    let returning = false;
    // Until then, follow the head while it grows or shrinks with the camera video.
    let videofade = -Infinity;
    // Until then, follow the avatar's size while a costume model loads after a change.
    let settleuntil = -Infinity;
    let dirty = true;
    let videoshown = false;

    /**
     * Show the face to the viewer: at once with reduced motion, else by turning back.
     */
    const faceFront = () => {
        holduntil = performance.now() + HOLD_MS;
        touched = -Infinity;
        if (still()) {
            turntable.rotation.y = 0;
            returning = false;
        } else {
            turntable.rotation.y = wrapAngle(turntable.rotation.y);
            returning = true;
        }
        dirty = true;
    };

    const draw = () => {
        renderer.render(scene, camera);
        dirty = false;
    };
    // The preview's frame loop: turntable, return to the front, framing and the webcam fade.
    // eslint-disable-next-line complexity
    const tick = (now) => {
        const dt = Math.min((now - last) / 1000, 0.1);
        last = now;
        let moved = false;
        if (returning && !dragging) {
            const angle = turntable.rotation.y;
            if (Math.abs(angle) < 0.002) {
                turntable.rotation.y = 0;
                returning = false;
            } else {
                turntable.rotation.y = angle - angle * Math.min(1, RETURN_RATE * dt);
            }
            moved = true;
        } else if (!dragging && !hovering && !focused && !still() && now >= holduntil && now - touched >= IDLE_MS) {
            turntable.rotation.y = wrapAngle(turntable.rotation.y + SPIN_SPEED * dt);
            moved = true;
        }
        const fading = now < videofade;
        const settling = now < settleuntil;
        if (fading || videoshown || settling) {
            if (typeof avatar.update === 'function') {
                avatar.update(now);
            }
            if (fading || settling) {
                frame();
            }
        }
        // A live camera picture changes every frame; a loading model appears when it arrives.
        if (moved || dirty || videoshown || fading || settling) {
            draw();
        }
        window.requestAnimationFrame(tick);
    };

    /**
     * The user turned the avatar by a number of radians.
     *
     * @param {number} by Radians.
     */
    const turn = (by) => {
        turntable.rotation.y = wrapAngle(turntable.rotation.y + by);
        returning = false;
        touched = performance.now();
        dirty = true;
    };

    canvas.addEventListener('pointerdown', (e) => {
        dragging = {x: e.clientX, id: e.pointerId};
        canvas.setPointerCapture(e.pointerId);
        returning = false;
    });
    canvas.addEventListener('pointermove', (e) => {
        if (dragging && dragging.id === e.pointerId) {
            turn((e.clientX - dragging.x) * DRAG_SPEED);
            dragging.x = e.clientX;
        }
    });
    const release = (e) => {
        if (dragging && dragging.id === e.pointerId) {
            dragging = null;
            touched = performance.now();
        }
    };
    canvas.addEventListener('pointerup', release);
    canvas.addEventListener('pointercancel', release);
    // The turntable stops while the pointer is on the preview or it has the focus.
    canvas.addEventListener('pointerenter', () => {
        hovering = true;
    });
    canvas.addEventListener('pointerleave', () => {
        hovering = false;
    });
    canvas.addEventListener('focus', () => {
        focused = true;
    });
    canvas.addEventListener('blur', () => {
        focused = false;
    });
    canvas.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
            turn(e.key === 'ArrowLeft' ? -KEY_STEP : KEY_STEP);
            e.preventDefault();
        }
    });
    if (reducedmotion && typeof reducedmotion.addEventListener === 'function') {
        reducedmotion.addEventListener('change', () => {
            if (still()) {
                faceFront();
            }
        });
    }

    if (window.ResizeObserver) {
        new ResizeObserver(() => {
            resize();
            dirty = true;
        }).observe(canvas);
    } else {
        window.addEventListener('resize', () => {
            resize();
            dirty = true;
        });
    }
    turntable.rotation.y = 0;
    resize();
    draw();
    window.requestAnimationFrame(tick);

    /**
     * Frame the avatar again once a loading costume model has arrived, when the builder says so.
     *
     * @param {*} result What the builder returned: a promise when it loads a model.
     */
    const whenLoaded = (result) => {
        settleuntil = performance.now() + SETTLE_MS;
        // The builder's whenDressed() waits for every model asked for (costume and model hat).
        const dressed = typeof avatar.whenDressed === 'function' ? avatar.whenDressed() : result;
        if (dressed && typeof dressed.then === 'function') {
            Promise.resolve(dressed).then(() => {
                frame();
                dirty = true;
                return null;
            }).catch((e) => {
                Log.debug('local_vravatar: costume model: ' + e);
            });
        }
    };
    whenLoaded(avatar.ready);

    return {
        update: (next, nextassets) => {
            whenLoaded(avatar.setAppearance(next, next.picture ? config.pictureurl : '', nextassets));
            frame();
            faceFront();
        },
        setVideo: (video) => {
            avatar.setVideo(video);
            videoshown = !!video;
            // The head grows or shrinks with the video's fade: follow it a little longer than that.
            videofade = performance.now() + (AvatarModel.FADE_MS || 300) + 400;
            faceFront();
        },
        setWhole: (value) => {
            whole = !!value;
            frame();
            faceFront();
        },
    };
};

/**
 * The zoom button: shows the whole avatar or the head-and-shoulders close-up.
 *
 * @param {HTMLButtonElement} button The button.
 * @param {Object} preview The preview.
 */
const setupZoom = (button, preview) => {
    // A toggle button: the label stays "Show the whole avatar", aria-pressed (and the active look)
    // says whether it is on.
    let whole = false;
    button.hidden = false;
    button.addEventListener('click', () => {
        whole = !whole;
        preview.setWhole(whole);
        button.setAttribute('aria-pressed', whole ? 'true' : 'false');
        button.classList.toggle('active', whole);
    });
};

/**
 * The camera preview button: shows the user's own camera on the face screen of the preview, as
 * the others in a live room would see it. Nothing is sent.
 *
 * @param {HTMLButtonElement} button The button.
 * @param {Object} preview The preview.
 * @param {Function} status Sets the status line.
 * @param {Object} str Strings.
 */
const setupCamera = (button, preview, status, str) => {
    if (!navigator.mediaDevices || typeof navigator.mediaDevices.getUserMedia !== 'function') {
        return;
    }
    let stream = null;
    let video = null;
    const stop = () => {
        if (stream) {
            stream.getTracks().forEach((track) => track.stop());
        }
        stream = null;
        video = null;
        preview.setVideo(null);
        button.setAttribute('aria-pressed', 'false');
        button.textContent = str.camerapreview;
    };
    button.hidden = false;
    button.addEventListener('click', async() => {
        if (stream) {
            stop();
            status(str.camera_off);
            return;
        }
        button.disabled = true;
        try {
            stream = await navigator.mediaDevices.getUserMedia({video: VIDEO, audio: false});
            video = document.createElement('video');
            video.muted = true;
            video.playsInline = true;
            video.srcObject = stream;
            await video.play();
            preview.setVideo(video);
            button.setAttribute('aria-pressed', 'true');
            button.textContent = str.camerapreviewstop;
            status(str.camera_on);
        } catch (e) {
            Log.debug('local_vravatar: camera preview: ' + e);
            stop();
            status(str.camera_error);
        } finally {
            button.disabled = false;
        }
    });
    window.addEventListener('pagehide', stop);
};

/**
 * Start the designer.
 *
 * @param {Object} config {design, defaults, pictureurl, pictureallowed, name, seed}
 */
export const init = async(config) => {
    const pending = new Pending('local_vravatar/designer:init');
    const root = document.querySelector('[data-region="local-vravatar-designer"]');
    if (!root) {
        pending.resolve();
        return;
    }
    const form = root.querySelector('[data-region="form"]');
    const statusline = root.querySelector('[data-region="status"]');
    const resetbutton = root.querySelector('[data-action="reset"]');
    const savebutton = root.querySelector('[data-action="save"]');
    const removeform = root.querySelector('[data-region="removeform"]');
    const nodesign = root.querySelector('[data-region="nodesign"]');
    const canvas = root.querySelector('[data-region="canvas"]');
    const fallback = root.querySelector('[data-region="fallback"]');
    const previewhelp = root.querySelector('[data-region="previewhelp"]');
    const camerabutton = root.querySelector('[data-action="camera"]');
    const zoombutton = root.querySelector('[data-action="zoom"]');

    const values = await getStrings(STRINGS.map((key) => ({key, component: 'local_vravatar'})));
    const str = {};
    STRINGS.forEach((key, i) => {
        str[key] = values[i];
    });
    const status = (text) => {
        statusline.textContent = text;
    };

    paintSwatches(root);

    let preview = null;
    if (hasWebgl()) {
        canvas.hidden = false;
        preview = createPreview(canvas, readForm(form, config.defaults), readAssets(form), config);
        if (preview) {
            fallback.hidden = true;
            previewhelp.hidden = false;
            setupZoom(zoombutton, preview);
            setupCamera(camerabutton, preview, status, str);
        } else {
            canvas.hidden = true;
        }
    }

    form.addEventListener('change', () => {
        if (preview) {
            preview.update(readForm(form, config.defaults), readAssets(form));
        }
    });

    resetbutton.hidden = false;
    resetbutton.addEventListener('click', () => {
        writeForm(form, config.defaults);
        if (preview) {
            preview.update(readForm(form, config.defaults), readAssets(form));
        }
        status(str.resetdone);
    });

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const savepending = new Pending('local_vravatar/designer:save');
        savebutton.disabled = true;
        status(str.saving);
        // A native promise: core/ajax gives a jQuery one, which has no finally().
        Promise.resolve(Ajax.call([{
            methodname: 'local_vravatar_save_design',
            args: {design: JSON.stringify(readForm(form, config.defaults))},
        }])[0]).then((result) => {
            writeForm(form, result.design);
            if (preview) {
                preview.update(readForm(form, config.defaults), readAssets(form));
            }
            removeform.hidden = false;
            if (nodesign) {
                nodesign.hidden = true;
            }
            status(str.saved);
            return result;
        }).catch((error) => {
            status('');
            Notification.exception(error);
        }).finally(() => {
            savebutton.disabled = false;
            savepending.resolve();
        });
    });

    pending.resolve();
};
