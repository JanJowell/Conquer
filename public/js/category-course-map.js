import {
    MAX_POINTS, MAX_CHECKPOINTS, MAX_LABEL_LENGTH,
    emptyCourse, copyCourse, coordinate, validateCourse, parseCourse, courseSummary,
} from './course-map-data.js';

// One delegated listener also handles new category rows and Livewire navigation.
const state = window.__racetechCourseEditor ??= { controllers: new Map(), loader: null, bound: false, submitting: false };

function loadGoogleMaps(key) {
    if (state.authFailed) return Promise.reject(new Error('Google Maps rejected the key. Check billing, API restrictions, and the allowed website, then reload this page.'));
    if (window.google?.maps?.Map) return Promise.resolve(window.google.maps);
    if (state.loader) return state.loader;
    state.loader = new Promise((resolve, reject) => {
        const script = document.createElement('script');
        const fail = (message) => {
            clearTimeout(timeout);
            script.remove();
            delete window.__racetechMapsReady;
            state.loader = null;
            reject(new Error(message));
        };
        const timeout = setTimeout(() => fail('Google Maps did not load. Check your connection and try again.'), 20000);
        window.__racetechMapsReady = () => {
            clearTimeout(timeout);
            delete window.__racetechMapsReady;
            resolve(window.google.maps);
        };
        const previousAuthFailure = window.gm_authFailure;
        window.gm_authFailure = () => {
            state.authFailed = true;
            fail('Google Maps rejected the key. Check billing, API restrictions, and the allowed website, then reload this page.');
            document.dispatchEvent(new Event('racetech:map-auth-failure'));
            previousAuthFailure?.();
        };
        const url = new URL('https://maps.googleapis.com/maps/api/js');
        url.search = new URLSearchParams({ key, v: 'quarterly', loading: 'async', callback: '__racetechMapsReady' });
        script.src = url.toString();
        script.async = true;
        script.onerror = () => fail('Google Maps could not be downloaded. Check your connection and try again.');
        document.head.append(script);
    });
    return state.loader;
}

function mapLabel(maps, map, position, label, color) {
    // Supported OverlayView labels avoid the retired Drawing Library and do not
    // require another API, a Map ID, or deprecated google.maps.Marker.
    const overlay = new maps.OverlayView();
    overlay.onAdd = () => {
        overlay.element = document.createElement('span');
        overlay.element.textContent = label;
        overlay.element.style.cssText = `position:absolute;transform:translate(-50%,-125%);border:2px solid white;border-radius:12px;padding:4px 8px;white-space:nowrap;font:600 12px/1.4 sans-serif;color:white;background:${color};box-shadow:0 1px 5px #0005;pointer-events:none;`;
        overlay.getPanes().overlayLayer.append(overlay.element);
    };
    overlay.draw = () => {
        const pixel = overlay.getProjection().fromLatLngToDivPixel(new maps.LatLng(position));
        if (pixel && overlay.element) {
            overlay.element.style.left = `${pixel.x}px`;
            overlay.element.style.top = `${pixel.y}px`;
        }
    };
    overlay.onRemove = () => overlay.element?.remove();
    overlay.setMap(map);
    return overlay;
}

class CourseEditor {
    constructor(root) {
        this.root = root;
        this.input = root.querySelector('[data-course-map-value]');
        this.panel = root.querySelector('[data-course-map-panel]');
        this.status = root.querySelector('[data-course-map-status]');
        this.reviewed = root.querySelector('[data-course-reviewed]');
        this.history = [];
        this.labels = [];
        this.mode = 'draw';
        this.staged = false;
        try {
            this.saved = parseCourse(this.input.value);
            this.draft = copyCourse(this.saved);
        } catch {
            this.saved = null;
            this.draft = emptyCourse();
            this.message('The previous course data is invalid. Remove it explicitly or draw and apply a replacement.', true);
        }
    }

    get dirty() {
        return JSON.stringify(this.draft) !== JSON.stringify(this.saved ?? emptyCourse());
    }

    message(text, error = false) {
        this.status.textContent = text;
        this.status.style.color = error ? '#be123c' : '#475569';
        this.status.setAttribute('role', error ? 'alert' : 'status');
    }

    remember() {
        this.history.push(copyCourse(this.draft));
        if (this.history.length > 30) this.history.shift();
    }

    changed({ list = true } = {}) {
        state.submitting = false;
        this.reviewed.checked = false;
        this.drawLabels();
        if (list) this.drawCheckpoints();
        this.message(`${this.draft.path.length} route points, ${this.draft.checkpoints.length} checkpoints. Review and use this route before saving.`);
    }

    async open() {
        if (this.loading) return;
        if (!this.root.dataset.apiKey) throw new Error('The map editor is not configured. Add GOOGLE_MAPS_BROWSER_KEY to the server environment.');
        this.panel.hidden = false;
        if (this.map) {
            this.maps.event.trigger(this.map, 'resize');
            this.fit();
            return;
        }
        this.loading = true;
        this.message('Loading Google Maps...');
        try {
            this.maps = await loadGoogleMaps(this.root.dataset.apiKey);
            if (!this.root.isConnected || this.panel.hidden) return;
            this.map = new this.maps.Map(this.root.querySelector('[data-course-map-canvas]'), {
                center: this.draft.path[0] ?? { lat: 14.46, lng: 120.96 },
                zoom: 13, mapTypeId: 'roadmap', streetViewControl: false,
                mapTypeControl: false, fullscreenControl: true, gestureHandling: 'cooperative',
            });
            this.line = new this.maps.Polyline({
                map: this.map, path: this.draft.path, editable: true,
                strokeColor: '#4f46e5', strokeOpacity: 0.9, strokeWeight: 5,
            });
            const path = this.line.getPath();
            path.addListener('insert_at', (index) => {
                if (this.syncing) return;
                if (path.getLength() > MAX_POINTS) {
                    this.syncLine();
                    this.message(`The course supports up to ${MAX_POINTS} points.`, true);
                    return;
                }
                this.remember();
                this.draft.path.splice(index, 0, coordinate(path.getAt(index).lat(), path.getAt(index).lng()));
                this.changed();
            });
            path.addListener('set_at', (index) => {
                if (this.syncing) return;
                this.remember();
                this.draft.path[index] = coordinate(path.getAt(index).lat(), path.getAt(index).lng());
                this.changed();
            });
            path.addListener('remove_at', (index) => {
                if (this.syncing) return;
                this.remember();
                this.draft.path.splice(index, 1);
                this.changed();
            });
            this.map.addListener('click', (event) => this.addAt(event.latLng));
            this.line.addListener('click', (event) => {
                if (this.mode !== 'draw') this.addAt(event.latLng);
            });
            this.line.addListener('contextmenu', (event) => {
                if (this.mode === 'draw' && Number.isInteger(event.vertex)) path.removeAt(event.vertex);
            });
            this.drawCheckpoints();
            this.drawLabels();
            this.setMode('draw');
            this.fit();
            this.message('Choose Draw Course and click along the course, or add checkpoints.');
        } finally {
            this.loading = false;
        }
    }

    syncLine() {
        if (!this.line) return;
        this.setMode('draw');
        this.syncing = true;
        const path = this.line.getPath();
        path.clear();
        this.draft.path.forEach((point) => path.push(new this.maps.LatLng(point)));
        this.syncing = false;
        this.drawLabels();
        this.drawCheckpoints();
    }

    addAt(latLng) {
        if (!latLng || this.mode === 'preview') return;
        const point = coordinate(latLng.lat(), latLng.lng());
        if (this.mode === 'draw') {
            if (this.draft.path.length >= MAX_POINTS) return this.message(`The course supports up to ${MAX_POINTS} points.`, true);
            this.line.getPath().push(latLng);
        } else if (this.mode === 'checkpoint') {
            if (this.draft.path.length < 2) return this.message('Draw the course before adding checkpoints.', true);
            if (this.draft.checkpoints.length >= MAX_CHECKPOINTS) return this.message(`A course supports up to ${MAX_CHECKPOINTS} checkpoints.`, true);
            this.remember();
            this.draft.checkpoints.push({ ...point, label: `Checkpoint ${this.draft.checkpoints.length + 1}` });
            this.changed();
        } else if (this.mode === 'move') {
            if (!this.draft.checkpoints[this.moving]) return this.setMode('checkpoint');
            this.remember();
            this.draft.checkpoints[this.moving] = { ...this.draft.checkpoints[this.moving], ...point };
            this.changed();
            this.setMode('checkpoint');
        }
    }

    setMode(mode) {
        if (!this.map) return this.message('Open the map editor and wait for it to load first.', true);
        this.mode = mode;
        this.line.setEditable(mode === 'draw');
        this.root.querySelectorAll('[data-course-action]').forEach((button) => {
            if (['draw', 'checkpoint', 'preview'].includes(button.dataset.courseAction)) {
                button.setAttribute('aria-pressed', String(button.dataset.courseAction === mode));
            }
        });
        this.message({ draw: 'Click the map to extend the course. Drag line handles to edit.', checkpoint: 'Click the map or course to add a checkpoint.', preview: 'Preview the complete course, Start/Finish, and checkpoint labels.', move: 'Click the map or course to move the selected checkpoint.' }[mode]);
    }

    drawLabels() {
        this.labels.forEach((label) => label.setMap(null));
        this.labels = [];
        if (!this.map || !this.draft.path.length) return;
        const start = this.draft.path[0];
        const finish = this.draft.path.at(-1);
        const loop = this.draft.path.length > 1 && start.lat === finish.lat && start.lng === finish.lng;
        this.labels.push(mapLabel(this.maps, this.map, start, loop ? 'Start / Finish' : 'Start', '#047857'));
        if (!loop && this.draft.path.length > 1) this.labels.push(mapLabel(this.maps, this.map, finish, 'Finish', '#be123c'));
        this.draft.checkpoints.forEach((point, index) => {
            this.labels.push(mapLabel(this.maps, this.map, point, `${index + 1}. ${point.label}`, '#4338ca'));
        });
    }

    drawCheckpoints() {
        const list = this.root.querySelector('[data-course-checkpoints]');
        list.replaceChildren();
        if (!this.draft.checkpoints.length) {
            const empty = document.createElement('p');
            empty.className = 'text-sm text-slate-500';
            empty.textContent = 'No checkpoints added.';
            list.append(empty);
        }
        this.draft.checkpoints.forEach((point, index) => {
            const row = document.createElement('div');
            row.className = 'flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white p-2';
            const input = document.createElement('input');
            input.type = 'text';
            input.maxLength = MAX_LABEL_LENGTH;
            input.value = point.label;
            input.dataset.courseCheckpointLabel = index;
            input.setAttribute('aria-label', `Checkpoint ${index + 1} label`);
            input.className = 'min-h-11 min-w-0 flex-1 rounded-lg border border-slate-200 px-3 text-sm';
            const number = document.createElement('span');
            number.textContent = `${index + 1}.`;
            row.append(number, input);
            for (const [action, label] of [['move', 'Move'], ['delete-checkpoint', 'Remove']]) {
                const button = document.createElement('button');
                button.type = 'button';
                button.dataset.courseAction = action;
                button.dataset.checkpointIndex = index;
                button.textContent = label;
                button.setAttribute('aria-label', `${label} checkpoint ${index + 1}`);
                button.className = 'min-h-11 rounded-lg border border-slate-200 px-3 text-sm font-semibold text-slate-700';
                row.append(button);
            }
            list.append(row);
        });
    }

    fit() {
        if (!this.map || !this.draft.path.length) return;
        const bounds = new this.maps.LatLngBounds();
        [...this.draft.path, ...this.draft.checkpoints].forEach((point) => bounds.extend(point));
        this.map.fitBounds(bounds, 55);
    }

    summary() {
        const course = this.saved ?? emptyCourse();
        this.root.querySelector('[data-course-map-summary]').textContent = courseSummary(course);
        this.root.querySelector('[data-course-action="remove"]').hidden = !course.path.length;
    }

    async action(action, button) {
        if (action === 'open') return this.open();
        if (action === 'remove') {
            if (!window.confirm('Remove the drawn route? The uploaded image is kept. Save the category or event to confirm removal.')) return;
            this.input.value = '';
            this.saved = emptyCourse();
            this.draft = emptyCourse();
            this.history = [];
            this.staged = true;
            this.reviewed.checked = false;
            this.syncLine();
            this.summary();
            this.message('Route removal staged. Save the category or event to confirm.');
            return;
        }
        if (action === 'discard') {
            if (this.dirty && !window.confirm('Discard the current map draft? Your last applied route will be kept.')) return;
            this.draft = copyCourse(this.saved ?? emptyCourse());
            this.history = [];
            this.reviewed.checked = false;
            this.syncLine();
            this.panel.hidden = true;
            this.message('Editor closed. Save the category or event to persist any previously applied route.');
            return;
        }
        if (!this.map) return this.message('Wait for the map to load before editing. You can still close the editor.', true);
        if (['draw', 'checkpoint', 'preview'].includes(action)) return this.setMode(action);
        if (action === 'fit') return this.fit();
        if (action === 'center') {
            this.map.setCenter(coordinate(this.root.querySelector('[data-course-center-lat]').value, this.root.querySelector('[data-course-center-lng]').value));
            this.map.setZoom(14);
        } else if (action === 'undo') {
            if (!this.history.length) return this.message('There are no draft changes to undo.');
            this.draft = this.history.pop();
            this.syncLine();
            this.changed();
        } else if (action === 'close-loop') {
            if (this.draft.path.length < 2) return this.message('Draw at least two different route points first.', true);
            const first = this.draft.path[0];
            const last = this.draft.path.at(-1);
            if (first.lat === last.lat && first.lng === last.lng) return this.message('This course already finishes at Start.');
            if (this.draft.path.length >= MAX_POINTS) return this.message(`The course supports up to ${MAX_POINTS} points.`, true);
            this.line.getPath().push(new this.maps.LatLng(first));
        } else if (action === 'clear') {
            if (!window.confirm('Clear the draft course and all its checkpoints? Use Remove Saved Route to remove an existing saved course.')) return;
            this.remember();
            this.draft = emptyCourse();
            this.syncLine();
            this.changed();
        } else if (action === 'move') {
            this.moving = Number(button.dataset.checkpointIndex);
            this.setMode('move');
        } else if (action === 'delete-checkpoint') {
            this.remember();
            this.draft.checkpoints.splice(Number(button.dataset.checkpointIndex), 1);
            this.changed();
            if (this.mode === 'move') this.setMode('checkpoint');
        } else if (action === 'apply') {
            const normalized = validateCourse(this.draft);
            if (!this.reviewed.checked) return this.message('Review the course and check the confirmation before using it.', true);
            this.saved = normalized;
            this.draft = copyCourse(normalized);
            this.input.value = JSON.stringify(normalized);
            this.staged = true;
            this.history = [];
            this.syncLine();
            this.summary();
            this.panel.hidden = true;
            this.message('Route staged. Save the category or event to persist it.');
        }
    }

    dispose() {
        this.labels.forEach((label) => label.setMap(null));
        if (this.line) {
            this.maps.event.clearInstanceListeners(this.line.getPath());
            this.maps.event.clearInstanceListeners(this.line);
            this.line.setMap(null);
        }
        if (this.map) this.maps.event.clearInstanceListeners(this.map);
    }
}

function controller(root) {
    if (!state.controllers.has(root)) state.controllers.set(root, new CourseEditor(root));
    return state.controllers.get(root);
}

if (!state.bound) {
    state.bound = true;
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-course-action]');
        const root = button?.closest('[data-course-map-editor]');
        if (!root || button.disabled) return;
        event.preventDefault();
        const editor = controller(root);
        try { await editor.action(button.dataset.courseAction, button); }
        catch (error) { editor.message(error.message || 'The course could not be updated. Your applied route was kept.', true); }
    });
    document.addEventListener('focusin', (event) => {
        if (event.target.matches('[data-course-checkpoint-label]')) controller(event.target.closest('[data-course-map-editor]')).remember();
    });
    document.addEventListener('input', (event) => {
        if (!event.target.matches('[data-course-checkpoint-label]')) return;
        const editor = controller(event.target.closest('[data-course-map-editor]'));
        editor.draft.checkpoints[Number(event.target.dataset.courseCheckpointLabel)].label = event.target.value;
        editor.changed({ list: false });
    });
    document.addEventListener('submit', (event) => {
        const dirty = [...state.controllers.values()].find((editor) => editor.root.isConnected && event.target.contains(editor.root) && editor.dirty);
        if (dirty) {
            event.preventDefault();
            event.stopImmediatePropagation();
            dirty.message('Your map draft has not been applied. Use This Route or discard the draft before saving.', true);
            dirty.root.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else state.submitting = true;
    }, true);
    window.addEventListener('beforeunload', (event) => {
        if (!state.submitting && [...state.controllers.values()].some((editor) => editor.root.isConnected && (editor.dirty || editor.staged))) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
    document.addEventListener('racetech:map-auth-failure', () => {
        state.controllers.forEach((editor) => editor.message('Google Maps authentication failed. Check billing and key restrictions, then reload. Your applied route is unchanged.', true));
    });
    document.addEventListener('livewire:navigate', (event) => {
        const unsaved = [...state.controllers.values()].some((editor) => editor.root.isConnected && (editor.dirty || editor.staged));
        if (!state.submitting && unsaved && !window.confirm('Leave this page without saving your course-map changes?')) event.preventDefault();
    });
    document.addEventListener('livewire:navigating', () => {
        state.controllers.forEach((editor) => editor.dispose());
        state.controllers.clear();
        state.submitting = false;
    });
    new MutationObserver(() => {
        state.controllers.forEach((editor, root) => {
            if (!root.isConnected) { editor.dispose(); state.controllers.delete(root); }
        });
    }).observe(document.body, { childList: true, subtree: true });
}
