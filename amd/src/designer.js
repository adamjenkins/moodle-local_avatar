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
 * the local_avatar_save_design web service, Reset to default and a local camera preview.
 *
 * The preview is drawn by VR room's own avatar builder (vrroomruntime_builtin/live/avatarmodel), the
 * same module that draws the avatars in live rooms, so what a user designs is what others see. The
 * camera preview stays in this browser: nothing is sent anywhere.
 *
 * @module     local_avatar/designer
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

/** Turntable speed (radians per second). */
const SPIN_SPEED = 0.35;

/** Radians turned per pixel dragged. */
const DRAG_SPEED = 0.01;

/** Radians turned per arrow key press. */
const KEY_STEP = Math.PI / 12;

/** Field of view of the preview camera (degrees). */
const FOV = 30;

/** Camera video size for the preview, as in live rooms. */
const VIDEO = {width: {ideal: 320}, height: {ideal: 240}, frameRate: {ideal: 15}};

/** Keys of the document that take a name (radio groups). */
const CHOICES = ['body', 'head', 'color', 'accent', 'hair', 'haircolor', 'hat'];

/** Keys of the document that are true or false (checkboxes). */
const FLAGS = ['glasses', 'picture'];

/** The strings the page uses, in this order. */
const STRINGS = [
    'saving', 'saved', 'resetdone', 'camera_on', 'camera_off', 'camera_error', 'camerapreview', 'camerapreviewstop',
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
 * Paint the swatches with the avatar builder's own colours when it lists them, so a swatch shows
 * exactly the colour of the avatar.
 *
 * @param {HTMLElement} root The designer.
 */
const paintSwatches = (root) => {
    const palettes = [AvatarModel.COLOURS, AvatarModel.HAIR_COLOURS];
    root.querySelectorAll('[data-swatch]').forEach((swatch) => {
        const group = swatch.closest('fieldset');
        const ishair = group && group.id === 'local-avatar-group-haircolor';
        const palette = palettes[ishair ? 1 : 0];
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
 * The 3D preview: a turntable with the avatar, drag or arrow keys to turn it.
 *
 * @param {HTMLCanvasElement} canvas The canvas.
 * @param {Object} design The document to show first.
 * @param {Object} config Page config.
 * @returns {Object|null} {update(design), setVideo(video|null), avatar} or null when it cannot draw.
 */
const createPreview = (canvas, design, config) => {
    if (typeof AvatarModel.createAvatar !== 'function') {
        Log.warn('local_avatar: the VR room avatar builder is missing createAvatar');
        return null;
    }
    let renderer;
    try {
        renderer = new THREE.WebGLRenderer({canvas, antialias: true, alpha: true});
    } catch (e) {
        Log.warn('local_avatar: no WebGL for the preview: ' + e);
        return null;
    }
    renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    if ('outputColorSpace' in renderer && THREE.SRGBColorSpace) {
        renderer.outputColorSpace = THREE.SRGBColorSpace;
    }

    const scene = new THREE.Scene();
    scene.add(new THREE.HemisphereLight(0xffffff, 0x8899aa, 1.6));
    const sun = new THREE.DirectionalLight(0xffffff, 1.4);
    sun.position.set(1.5, 3, 2.5);
    scene.add(sun);

    const camera = new THREE.PerspectiveCamera(FOV, 3 / 4, 0.05, 50);
    const turntable = new THREE.Group();
    scene.add(turntable);

    const avatar = AvatarModel.createAvatar(THREE, design, {
        name: config.name,
        pictureurl: design.picture ? config.pictureurl : '',
        seed: config.seed,
    });
    turntable.add(avatar.group);

    // Frame the avatar: centre it on the turntable and back the camera off until it fits.
    const frame = () => {
        avatar.group.position.set(0, 0, 0);
        const box = new THREE.Box3().setFromObject(avatar.group);
        if (box.isEmpty()) {
            camera.position.set(0, 1.4, 3);
            camera.lookAt(0, 1.2, 0);
            return;
        }
        const centre = box.getCenter(new THREE.Vector3());
        const size = box.getSize(new THREE.Vector3());
        avatar.group.position.set(-centre.x, -centre.y, -centre.z);
        const height = Math.max(size.y, size.x / Math.max(camera.aspect, 0.1)) * 1.15;
        const distance = (height / 2) / Math.tan(THREE.MathUtils.degToRad(FOV / 2)) + size.z;
        camera.position.set(0, height * 0.08, distance);
        camera.lookAt(0, 0, 0);
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
    let dragging = null;
    let last = performance.now();
    let dirty = true;
    let videoshown = false;

    const draw = () => {
        renderer.render(scene, camera);
        dirty = false;
    };
    const tick = (now) => {
        const dt = Math.min((now - last) / 1000, 0.1);
        last = now;
        const spin = !dragging && !(reducedmotion && reducedmotion.matches);
        if (spin) {
            turntable.rotation.y += SPIN_SPEED * dt;
        }
        // A live camera picture changes every frame.
        if (spin || dirty || videoshown) {
            draw();
        }
        window.requestAnimationFrame(tick);
    };

    canvas.addEventListener('pointerdown', (e) => {
        dragging = {x: e.clientX, id: e.pointerId};
        canvas.setPointerCapture(e.pointerId);
    });
    canvas.addEventListener('pointermove', (e) => {
        if (dragging && dragging.id === e.pointerId) {
            turntable.rotation.y += (e.clientX - dragging.x) * DRAG_SPEED;
            dragging.x = e.clientX;
            dirty = true;
        }
    });
    const release = (e) => {
        if (dragging && dragging.id === e.pointerId) {
            dragging = null;
        }
    };
    canvas.addEventListener('pointerup', release);
    canvas.addEventListener('pointercancel', release);
    canvas.addEventListener('keydown', (e) => {
        if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
            turntable.rotation.y += e.key === 'ArrowLeft' ? -KEY_STEP : KEY_STEP;
            dirty = true;
            e.preventDefault();
        }
    });

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
    resize();
    draw();
    window.requestAnimationFrame(tick);

    return {
        update: (next) => {
            avatar.setAppearance(next, next.picture ? config.pictureurl : '');
            dirty = true;
        },
        setVideo: (video) => {
            avatar.setVideo(video);
            videoshown = !!video;
            dirty = true;
        },
    };
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
            Log.debug('local_avatar: camera preview: ' + e);
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
    const pending = new Pending('local_avatar/designer:init');
    const root = document.querySelector('[data-region="local-avatar-designer"]');
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

    const values = await getStrings(STRINGS.map((key) => ({key, component: 'local_avatar'})));
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
        preview = createPreview(canvas, readForm(form, config.defaults), config);
        if (preview) {
            fallback.hidden = true;
            previewhelp.hidden = false;
            setupCamera(camerabutton, preview, status, str);
        } else {
            canvas.hidden = true;
        }
    }

    form.addEventListener('change', () => {
        if (preview) {
            preview.update(readForm(form, config.defaults));
        }
    });

    resetbutton.hidden = false;
    resetbutton.addEventListener('click', () => {
        writeForm(form, config.defaults);
        if (preview) {
            preview.update(readForm(form, config.defaults));
        }
        status(str.resetdone);
    });

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const savepending = new Pending('local_avatar/designer:save');
        savebutton.disabled = true;
        status(str.saving);
        // A native promise: core/ajax gives a jQuery one, which has no finally().
        Promise.resolve(Ajax.call([{
            methodname: 'local_avatar_save_design',
            args: {design: JSON.stringify(readForm(form, config.defaults))},
        }])[0]).then((result) => {
            writeForm(form, result.design);
            if (preview) {
                preview.update(readForm(form, config.defaults));
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
