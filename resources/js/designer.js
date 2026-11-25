import { Canvas, FabricImage, IText, Rect, loadSVGFromString, util } from 'fabric';

/*
 * Designer: one Fabric canvas per print area, drawn at `pxPerCm` canvas pixels per
 * centimetre and exported at ~300 DPI by scaling up. Canvases live outside Alpine's
 * reactive state (proxying Fabric objects breaks them); Alpine only holds UI state.
 */

const register = () => window.Alpine.data('designer', designer);

if (window.Alpine) {
    register();
} else {
    document.addEventListener('alpine:init', register);
}

function designer() {
    const cfg = JSON.parse(document.getElementById('designer-config').textContent);
    const canvases = {};
    const history = {};
    const assetUrls = { ...cfg.assets };
    const colourCache = new Map();
    const storageKey = `designer:${cfg.productId}`;
    let loading = false;
    let saveTimer = null;

    const px = (cm) => Math.round(cm * cfg.pxPerCm);
    // Fabric 7 reports types lower-case and hyphenated ('i-text'); serialised JSON may use class names.
    const typeOf = (o) => String(o?.type ?? '').toLowerCase().replace('-', '');
    // Fabric 7 objects default to a centre origin, so the clip must say it starts at the top-left.
    const clipRect = (w, h) => new Rect({ left: 0, top: 0, originX: 'left', originY: 'top', width: w, height: h, absolutePositioned: true });
    const normHex = (c) => String(c || '').toLowerCase();
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    return {
        cfg,
        colourId: cfg.colourId,
        areaId: cfg.areas[0].id,
        zone: 'middle',
        selection: null,
        warnings: [],
        colourCounts: {},
        busy: false,
        saving: false,
        message: '',
        restored: false,
        designName: cfg.name ?? '',
        canUndo: false,
        canRedo: false,
        bracelet: { text: '', font: cfg.fonts[0] },
        fileInput: null,

        get area() {
            return cfg.areas.find((a) => a.id === this.areaId);
        },

        get mockup() {
            return this.area.mockups[this.colourId] ?? Object.values(this.area.mockups)[0] ?? '';
        },

        get hasErrors() {
            return this.warnings.some((w) => w.level === 'error');
        },

        get palette() {
            // Engraving is a single colour.
            return this.area.method === 'engraving' ? cfg.palette.slice(1, 2) : cfg.palette;
        },

        async init() {
            await this.loadFonts();

            for (const area of cfg.areas) {
                this.createCanvas(area);
            }

            // Reusing a design from another product starts from its artwork, scaled to this area.
            const seed = ! cfg.designId && cfg.seed ? cfg.seed : null;
            const saved = cfg.designId || seed ? null : this.readAutosave();

            for (const area of cfg.areas) {
                const source = seed?.areas?.[area.slug];
                const state = area.state ?? saved?.areas?.[area.id] ?? (source ? this.scaleState(source, area) : null);

                if (state) {
                    await this.restore(canvases[area.id], state);
                }

                this.pushHistory(canvases[area.id]);
            }

            if (saved) {
                this.restored = true;
                this.colourId = cfg.colours.some((c) => c.id === saved.colourId) ? saved.colourId : this.colourId;
            }

            if (cfg.textOnly) {
                this.initBracelet();
            }

            this.refresh();
        },

        /** Re-fit another product's artwork to this print area (same relative size and position). */
        scaleState(source, area) {
            const json = JSON.parse(source.state);
            const ratio = area.widthCm / source.widthCm;

            json.objects.forEach((o) => {
                ['left', 'top', 'scaleX', 'scaleY'].forEach((k) => {
                    if (typeof o[k] === 'number') {
                        o[k] *= ratio;
                    }
                });
            });

            return json;
        },

        async loadFonts() {
            try {
                await Promise.race([
                    Promise.all(cfg.fonts.map((f) => document.fonts.load(`40px "${f}"`))),
                    new Promise((resolve) => setTimeout(resolve, 3000)),
                ]);
            } catch {
                // Fall back to system fonts rather than blocking the designer.
            }
        },

        createCanvas(area) {
            const el = document.getElementById(`canvas-${area.id}`);
            const width = px(area.widthCm);
            const height = px(area.heightCm);

            const canvas = new Canvas(el, {
                width,
                height,
                preserveObjectStacking: true,
                selection: !cfg.textOnly,
                allowTouchScrolling: false,
            });

            canvas.clipPath = clipRect(width, height);
            canvases[area.id] = canvas;
            history[area.id] = { stack: [], index: -1 };

            const sync = () => this.updateSelection();
            canvas.on('selection:created', sync);
            canvas.on('selection:updated', sync);
            canvas.on('selection:cleared', sync);
            canvas.on('object:scaling', sync);
            canvas.on('object:rotating', sync);

            const changed = () => this.changed(canvas);
            canvas.on('object:modified', changed);
            canvas.on('object:added', changed);
            canvas.on('object:removed', changed);
            canvas.on('text:changed', changed);

            // Scale the canvas with the stage so it always fits, whatever the screen size.
            const box = el.closest('[data-box]');
            new ResizeObserver(() => {
                const w = box.clientWidth;

                if (w > 0) {
                    // Only the CSS size changes; the canvas keeps its logical size (and so its coordinates).
                    const cssWidth = `${w}px`;
                    const cssHeight = `${(w * height) / width}px`;

                    [canvas.wrapperEl, canvas.lowerCanvasEl, canvas.upperCanvasEl].forEach((node) => {
                        node.style.width = cssWidth;
                        node.style.height = cssHeight;
                    });

                    canvas.calcOffset();
                }
            }).observe(box);
        },

        // Serialising ------------------------------------------------------------

        serialise(canvas) {
            const data = canvas.toObject(['assetId', 'role']);

            data.objects.forEach((o) => {
                if (o.assetId && typeOf(o) === 'image') {
                    o.src = `asset:${o.assetId}`;
                }
            });

            delete data.clipPath;
            delete data.backgroundImage;

            return data;
        },

        async restore(canvas, state) {
            const json = typeof state === 'string' ? JSON.parse(state) : state;
            const wanted = json.objects.filter((o) => String(o.src ?? '').startsWith('asset:')).map((o) => o.src.slice(6));
            const missing = [...new Set(wanted)].filter((id) => ! assetUrls[id]);

            if (missing.length) {
                try {
                    const res = await fetch(`${cfg.urls.assetUrls}?ids=${missing.join(',')}`, { headers: { Accept: 'application/json' } });
                    Object.assign(assetUrls, await res.json());
                } catch {
                    // Images that cannot be refreshed are dropped below.
                }
            }

            json.objects = json.objects.filter((o) => {
                if (String(o.src ?? '').startsWith('asset:')) {
                    o.src = assetUrls[o.src.slice(6)];
                    o.crossOrigin = 'anonymous';

                    return Boolean(o.src);
                }

                return true;
            });

            loading = true;

            try {
                await canvas.loadFromJSON(json);
            } finally {
                loading = false;
            }

            canvas.clipPath = clipRect(canvas.width, canvas.height);
            canvas.requestRenderAll();
        },

        // Change tracking, history and autosave -----------------------------------

        changed(canvas) {
            if (loading) {
                return;
            }

            this.pushHistory(canvas);
            this.updateSelection();
            this.refresh();
            this.queueAutosave();
        },

        pushHistory(canvas) {
            const id = this.idOf(canvas);
            const h = history[id];
            const json = JSON.stringify(this.serialise(canvas));

            if (h.stack[h.index] === json) {
                return;
            }

            h.stack = h.stack.slice(0, h.index + 1);
            h.stack.push(json);

            if (h.stack.length > 40) {
                h.stack.shift();
            }

            h.index = h.stack.length - 1;
            this.syncUndo();
        },

        idOf(canvas) {
            return Number(Object.keys(canvases).find((k) => canvases[k] === canvas));
        },

        syncUndo() {
            const h = history[this.areaId];
            this.canUndo = h.index > 0;
            this.canRedo = h.index < h.stack.length - 1;
        },

        async undo() {
            await this.step(-1);
        },

        async redo() {
            await this.step(1);
        },

        async step(delta) {
            const h = history[this.areaId];
            const next = h.index + delta;

            if (next < 0 || next >= h.stack.length) {
                return;
            }

            h.index = next;
            await this.restore(canvases[this.areaId], h.stack[next]);
            this.syncUndo();
            this.updateSelection();
            this.refresh();
            this.queueAutosave();
        },

        queueAutosave() {
            clearTimeout(saveTimer);
            saveTimer = setTimeout(() => {
                try {
                    const areas = {};

                    cfg.areas.forEach((a) => {
                        areas[a.id] = JSON.stringify(this.serialise(canvases[a.id]));
                    });

                    localStorage.setItem(storageKey, JSON.stringify({ colourId: this.colourId, areas, ts: Date.now() }));
                } catch {
                    // Storage full or blocked: autosave is a convenience only.
                }
            }, 500);
        },

        readAutosave() {
            try {
                const data = JSON.parse(localStorage.getItem(storageKey) ?? 'null');
                const hasContent = data && Object.values(data.areas ?? {}).some((s) => JSON.parse(s).objects?.length);

                return hasContent ? data : null;
            } catch {
                return null;
            }
        },

        async startOver() {
            localStorage.removeItem(storageKey);

            for (const a of cfg.areas) {
                canvases[a.id].clear();
                canvases[a.id].clipPath = clipRect(canvases[a.id].width, canvases[a.id].height);
                history[a.id] = { stack: [], index: -1 };
                this.pushHistory(canvases[a.id]);
            }

            this.restored = false;

            if (cfg.textOnly) {
                this.initBracelet();
            }

            this.refresh();
        },

        // Selection --------------------------------------------------------------

        updateSelection() {
            const canvas = canvases[this.areaId];
            const o = canvas?.getActiveObject();

            if (! o || cfg.textOnly) {
                this.selection = null;

                return;
            }

            const textual = typeOf(o) === 'itext';

            this.selection = {
                kind: o.role ? 'role' : textual ? 'text' : typeOf(o) === 'image' ? 'image' : 'graphic',
                role: o.role ?? null,
                text: textual ? o.text : '',
                font: textual ? o.fontFamily : '',
                fill: textual ? o.fill : null,
                widthPct: Math.round((o.getScaledWidth() / canvas.width) * 100),
                angle: Math.round((((o.angle ?? 0) % 360) + 360) % 360),
            };
        },

        selectedObject() {
            return canvases[this.areaId]?.getActiveObject() ?? null;
        },

        setText(value) {
            const o = this.selectedObject();

            if (! o || typeOf(o) !== 'itext') {
                return;
            }

            const limit = this.area.method === 'engraving' ? this.area.maxChars : null;
            o.set('text', limit ? value.slice(0, limit) : value);
            canvases[this.areaId].requestRenderAll();
            this.changed(canvases[this.areaId]);
        },

        async setFont(font) {
            const o = this.selectedObject();

            if (! o || typeOf(o) !== 'itext') {
                return;
            }

            await document.fonts.load(`40px "${font}"`);
            o.set('fontFamily', font);
            canvases[this.areaId].requestRenderAll();
            this.changed(canvases[this.areaId]);
        },

        setFill(hex) {
            const o = this.selectedObject();

            if (! o || typeOf(o) !== 'itext') {
                return;
            }

            o.set('fill', hex);
            canvases[this.areaId].requestRenderAll();
            this.changed(canvases[this.areaId]);
        },

        setWidth(pct) {
            const o = this.selectedObject();
            const canvas = canvases[this.areaId];

            if (! o) {
                return;
            }

            const scale = ((pct / 100) * canvas.width) / o.width;
            o.set({ scaleX: scale, scaleY: scale });
            o.setCoords();
            canvas.requestRenderAll();
            this.selection.widthPct = Math.round(pct);
            this.changed(canvas);
        },

        setAngle(angle) {
            const o = this.selectedObject();

            if (! o) {
                return;
            }

            o.rotate(Number(angle));
            o.setCoords();
            canvases[this.areaId].requestRenderAll();
            this.selection.angle = Math.round(angle);
            this.changed(canvases[this.areaId]);
        },

        // Placement: front and back have chest/middle/bottom bands the artwork can be moved to.
        zoneCentre(canvas, zone = this.zone) {
            return canvas.height * ({ top: 1 / 6, middle: 1 / 2, bottom: 5 / 6 }[zone] ?? 1 / 2);
        },

        placeIn(zone) {
            this.zone = zone;
            const canvas = canvases[this.areaId];
            const o = this.selectedObject();

            if (!o) {
                return;
            }

            o.setCoords();
            const box = o.getBoundingRect();
            const half = box.height / 2;
            const target = Math.min(Math.max(this.zoneCentre(canvas, zone), half), Math.max(canvas.height - half, half));

            o.set({ top: o.top + (target - (box.top + half)) });
            o.setCoords();
            canvas.requestRenderAll();
            this.changed(canvas);
        },

        centre() {
            const o = this.selectedObject();

            if (o) {
                canvases[this.areaId].centerObjectH(o);
                o.setCoords();
                this.changed(canvases[this.areaId]);
            }
        },

        forward() {
            const o = this.selectedObject();

            if (o) {
                canvases[this.areaId].bringObjectForward(o);
                this.changed(canvases[this.areaId]);
            }
        },

        backward() {
            const o = this.selectedObject();

            if (o) {
                canvases[this.areaId].sendObjectBackwards(o);
                this.changed(canvases[this.areaId]);
            }
        },

        remove() {
            const canvas = canvases[this.areaId];
            const active = canvas.getActiveObjects();

            if (active.length) {
                canvas.discardActiveObject();
                active.forEach((o) => canvas.remove(o));
                canvas.requestRenderAll();
            }
        },

        // Adding content ---------------------------------------------------------

        defaultFill() {
            const hex = (this.colours.find((c) => c.id === this.colourId)?.hex ?? '#ffffff').replace('#', '');
            const [r, g, b] = [0, 2, 4].map((i) => parseInt(hex.slice(i, i + 2), 16));
            const light = (r * 299 + g * 587 + b * 114) / 1000 > 140;

            return this.area.method === 'engraving' ? '#111111' : light ? '#111111' : '#FFFFFF';
        },

        get colours() {
            return cfg.colours;
        },

        async addText(text = 'Your text', role = null) {
            const canvas = canvases[this.areaId];
            const font = cfg.fonts[0];
            await document.fonts.load(`40px "${font}"`);

            const limit = this.area.method === 'engraving' ? this.area.maxChars : null;
            const obj = new IText(limit ? text.slice(0, limit) : text, {
                left: canvas.width / 2,
                top: this.area.zones ? this.zoneCentre(canvas) : canvas.height / 2,
                originX: 'center',
                originY: 'center',
                fontFamily: font,
                fontSize: Math.round(canvas.width * 0.14),
                fill: this.defaultFill(),
                textAlign: 'center',
                editable: true,
            });

            obj.role = role;

            canvas.add(obj);
            canvas.setActiveObject(obj);
            canvas.requestRenderAll();
        },

        addRole(role) {
            const exists = canvases[this.areaId].getObjects().some((o) => o.role === role);

            if (exists) {
                this.message = `This area already has a ${role} placeholder.`;

                return;
            }

            this.addText(role === 'name' ? 'NAME' : '10', role);
        },

        pickImage() {
            this.$refs.file.click();
        },

        async onFile(event) {
            const file = event.target.files?.[0];
            event.target.value = '';

            if (! file) {
                return;
            }

            if (file.size > cfg.maxUploadMb * 1048576) {
                this.message = `That file is too large. The limit is ${cfg.maxUploadMb} MB.`;

                return;
            }

            this.busy = true;
            this.message = '';

            try {
                const form = new FormData();
                form.append('file', file);

                const res = await fetch(cfg.urls.upload, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
                    body: form,
                });
                const data = await res.json().catch(() => ({}));

                if (! res.ok) {
                    this.message = data.message ?? 'That image could not be uploaded.';

                    return;
                }

                assetUrls[data.id] = data.url;
                await this.addAsset(data);
            } catch {
                this.message = 'The upload failed. Check your connection and try again.';
            } finally {
                this.busy = false;
            }
        },

        async addAsset(asset) {
            const canvas = canvases[this.areaId];
            let obj;

            if (asset.kind === 'svg') {
                const text = await (await fetch(asset.url)).text();
                const { objects, options } = await loadSVGFromString(text);
                obj = util.groupSVGElements(objects.filter(Boolean), options);
            } else {
                obj = await FabricImage.fromURL(asset.url, { crossOrigin: 'anonymous' });
            }

            const fit = Math.min((canvas.width * 0.6) / obj.width, (canvas.height * 0.6) / obj.height);
            const half = (obj.height * fit) / 2;
            const top = this.area.zones ? Math.min(Math.max(this.zoneCentre(canvas), half), Math.max(canvas.height - half, half)) : canvas.height / 2;
            obj.set({ scaleX: fit, scaleY: fit, left: canvas.width / 2, top, originX: 'center', originY: 'center' });
            obj.assetId = asset.id;

            canvas.add(obj);
            canvas.setActiveObject(obj);
            canvas.requestRenderAll();
        },

        // Bracelet (text only) ---------------------------------------------------

        initBracelet() {
            const canvas = canvases[this.areaId];
            const existing = canvas.getObjects().find((o) => typeOf(o) === 'itext');

            this.bracelet.text = existing?.text ?? '';
            this.bracelet.font = existing?.fontFamily ?? cfg.fonts[0];
        },

        async applyBracelet() {
            const canvas = canvases[this.areaId];
            const limit = this.area.maxChars ?? 40;
            const text = this.bracelet.text.slice(0, limit);
            this.bracelet.text = text;

            await document.fonts.load(`40px "${this.bracelet.font}"`);
            canvas.getObjects().forEach((o) => canvas.remove(o));

            if (text.trim() === '') {
                return;
            }

            const obj = new IText(text, {
                originX: 'center', originY: 'center', left: canvas.width / 2, top: canvas.height / 2,
                fontFamily: this.bracelet.font, fontSize: canvas.height * 0.7, fill: '#111111',
                selectable: false, evented: false, editable: false,
            });

            const maxWidth = canvas.width * 0.92;

            if (obj.width > maxWidth) {
                const scale = maxWidth / obj.width;
                obj.set({ scaleX: scale, scaleY: scale });
            }

            canvas.add(obj);
            canvas.requestRenderAll();
        },

        // Areas, colours ---------------------------------------------------------

        switchArea(id) {
            canvases[this.areaId]?.discardActiveObject();
            canvases[this.areaId]?.requestRenderAll();
            this.areaId = id;
            this.zone = 'middle';
            this.selection = null;
            this.syncUndo();
            this.refresh();

            if (cfg.textOnly) {
                this.initBracelet();
            }
        },

        setColour(id) {
            this.colourId = id;
            this.queueAutosave();
        },

        // Print rules ------------------------------------------------------------

        estimateImageColours(obj) {
            const el = obj.getElement?.();

            if (! el) {
                return 1;
            }

            const key = el.currentSrc || el.src;

            if (colourCache.has(key)) {
                return colourCache.get(key);
            }

            const size = 48;
            const c = document.createElement('canvas');
            c.width = c.height = size;
            const ctx = c.getContext('2d', { willReadFrequently: true });
            ctx.drawImage(el, 0, 0, size, size);
            const data = ctx.getImageData(0, 0, size, size).data;
            const buckets = new Map();
            let opaque = 0;

            for (let i = 0; i < data.length; i += 4) {
                if (data[i + 3] < 128) {
                    continue;
                }

                opaque++;
                const bucket = ((data[i] >> 5) << 6) | ((data[i + 1] >> 5) << 3) | (data[i + 2] >> 5);
                buckets.set(bucket, (buckets.get(bucket) ?? 0) + 1);
            }

            const count = Math.max(1, [...buckets.values()].filter((n) => n >= opaque * 0.03).length);
            colourCache.set(key, count);

            return count;
        },

        countColours(canvas) {
            const fills = new Set();
            let fromImages = 0;

            canvas.getObjects().forEach((o) => {
                if (o.role) {
                    return;
                }

                if (typeOf(o) === 'itext') {
                    fills.add(normHex(o.fill));
                } else if (typeOf(o) === 'image') {
                    fromImages += this.estimateImageColours(o);
                } else if (typeOf(o) === 'group') {
                    o.getObjects().forEach((child) => child.fill && child.fill !== 'none' && fills.add(normHex(child.fill)));
                }
            });

            return fills.size + fromImages;
        },

        refresh() {
            const warnings = [];
            const counts = {};

            for (const area of cfg.areas) {
                const canvas = canvases[area.id];
                const objects = canvas.getObjects().filter((o) => ! o.role);
                counts[area.id] = this.countColours(canvas);

                objects.forEach((o) => {
                    if (typeOf(o) === 'image') {
                        const dpi = Math.round((2.54 * cfg.pxPerCm) / Math.abs(o.scaleX || 1));

                        if (dpi < cfg.minImageDpi) {
                            warnings.push({ level: 'warn', text: `${area.name}: an image would print at about ${dpi} DPI. It may look soft; use a larger image or make it smaller.` });
                        }
                    }

                    if (typeOf(o) === 'itext' && area.method === 'embroidery' && area.minLineMm) {
                        const mm = (o.fontSize * Math.abs(o.scaleY || 1)) / cfg.pxPerCm * 10;

                        if (mm < area.minLineMm) {
                            warnings.push({ level: 'error', text: `${area.name}: text is too small to embroider (${mm.toFixed(1)} mm, minimum ${area.minLineMm} mm). Make it bigger.` });
                        }
                    }
                });

                if (area.method === 'embroidery' && area.maxColours && counts[area.id] > area.maxColours) {
                    warnings.push({ level: 'error', text: `${area.name}: embroidery allows ${area.maxColours} colours and this uses ${counts[area.id]}. Remove colours or use a simpler image.` });
                }

                if (area.method === 'engraving' && area.maxChars) {
                    const chars = objects.filter((o) => typeOf(o) === 'itext').reduce((n, o) => n + o.text.length, 0);

                    if (chars > area.maxChars) {
                        warnings.push({ level: 'error', text: `${area.name}: engraving allows ${area.maxChars} characters.` });
                    }
                }
            }

            this.warnings = warnings;
            this.colourCounts = counts;
            this.syncUndo();
        },

        // Saving -----------------------------------------------------------------

        personalisation(canvas, area) {
            const result = {};

            canvas.getObjects().filter((o) => o.role).forEach((o) => {
                const box = o.getBoundingRect();
                result[o.role] = {
                    x_cm: +(box.left / cfg.pxPerCm).toFixed(2),
                    y_cm: +(box.top / cfg.pxPerCm).toFixed(2),
                    width_cm: +(box.width / cfg.pxPerCm).toFixed(2),
                    height_cm: +(box.height / cfg.pxPerCm).toFixed(2),
                    font: o.fontFamily,
                    font_height_mm: +((o.fontSize * Math.abs(o.scaleY || 1)) / cfg.pxPerCm * 10).toFixed(1),
                    fill: o.fill,
                    angle: Math.round(o.angle || 0),
                    sample: o.text,
                };
            });

            return Object.keys(result).length ? result : null;
        },

        async exportArea(canvas) {
            const roles = canvas.getObjects().filter((o) => o.role);
            roles.forEach((o) => { o.visible = false; });
            canvas.discardActiveObject();
            canvas.renderAll();

            const target = cfg.exportDpi / 2.54 / cfg.pxPerCm;
            const cap = Math.sqrt(cfg.maxExportPixels / (canvas.width * canvas.height));
            const el = canvas.toCanvasElement(Math.min(target, cap));

            roles.forEach((o) => { o.visible = true; });
            canvas.renderAll();

            const blob = await new Promise((resolve) => el.toBlob(resolve, 'image/png'));
            el.width = el.height = 0;

            return blob;
        },

        async save() {
            this.message = '';
            this.refresh();

            if (this.hasErrors) {
                this.message = 'Fix the problems marked in red before you continue.';

                return;
            }

            const used = cfg.areas.filter((a) => canvases[a.id].getObjects().length > 0);

            if (! used.length) {
                this.message = 'Add an image or some text first.';

                return;
            }

            this.saving = true;

            try {
                const form = new FormData();
                form.append('product_id', cfg.productId);
                form.append('colour_id', this.colourId);
                form.append('name', this.designName);

                if (cfg.seed && cfg.urls.method !== 'PUT') {
                    form.append('group_from', cfg.seed.groupFrom);
                }

                if (cfg.urls.method === 'PUT') {
                    form.append('_method', 'PUT');
                }

                let i = 0;

                for (const area of used) {
                    const canvas = canvases[area.id];
                    const blob = await this.exportArea(canvas);
                    const roles = this.personalisation(canvas, area);

                    form.append(`areas[${i}][print_area_id]`, area.id);
                    form.append(`areas[${i}][state]`, JSON.stringify(this.serialise(canvas)));
                    form.append(`areas[${i}][colours_count]`, this.colourCounts[area.id] ?? 0);
                    form.append(`areas[${i}][export]`, blob, `area-${area.id}.png`);

                    if (roles) {
                        form.append(`areas[${i}][personalisation]`, JSON.stringify(roles));
                    }

                    i++;
                }

                const res = await fetch(cfg.urls.save, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
                    body: form,
                });
                const data = await res.json().catch(() => ({}));

                if (! res.ok) {
                    this.message = data.message ?? 'Your design could not be saved. Please try again.';

                    return;
                }

                localStorage.removeItem(storageKey);
                window.location = data.next;
            } catch {
                this.message = 'Saving failed. Check your connection and try again.';
            } finally {
                this.saving = false;
            }
        },
    };
}
