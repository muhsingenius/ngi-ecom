/*
 * Order options: how many of which size and colour, or a roster of names and numbers.
 * Prices come from the server (/cart/quote) so tiers that span designs or the whole
 * order are always right; this script only formats them.
 */

const register = () => window.Alpine.data('orderForm', orderForm);

if (window.Alpine) {
    register();
} else {
    document.addEventListener('alpine:init', register);
}

function orderForm(cfg) {
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    let timer = null;
    let controller = null;

    return {
        cfg,
        mode: cfg.needsName || cfg.needsNumber ? 'roster' : 'sizes',
        colourIds: [cfg.colourId],
        qty: {},
        rows: [],
        notes: cfg.notes ?? '',
        quote: null,
        quoting: false,
        previewIndex: 0,
        message: '',

        init() {
            const existing = cfg.existing ?? [];
            const personalised = existing.some((i) => (i.name ?? '') !== '' || (i.number ?? '') !== '');

            if (existing.length) {
                const variantInfo = this.variantIndex();

                if (personalised || this.mode === 'roster') {
                    this.mode = 'roster';
                    this.rows = existing.map((i) => ({ name: i.name ?? '', number: i.number ?? '', size: variantInfo[i.variant_id]?.size, colour: variantInfo[i.variant_id]?.colour }));
                } else {
                    this.colourIds = [...new Set(existing.map((i) => variantInfo[i.variant_id]?.colour).filter(Boolean))];

                    existing.forEach((i) => {
                        const v = variantInfo[i.variant_id];

                        if (v) {
                            this.qty[`${v.colour}:${v.size}`] = i.qty;
                        }
                    });
                }
            }

            if (this.mode === 'roster' && ! this.rows.length) {
                this.addRows(1);
            }

            this.$watch('items', () => this.scheduleQuote());
            this.scheduleQuote();
        },

        variantIndex() {
            const index = {};

            Object.entries(cfg.variants).forEach(([colour, sizes]) => {
                Object.entries(sizes).forEach(([size, v]) => {
                    index[v.id] = { colour: Number(colour), size };
                });
            });

            return index;
        },

        variantFor(colour, size) {
            return cfg.variants[colour]?.[size] ?? null;
        },

        get items() {
            if (this.mode === 'roster') {
                return this.rows
                    .map((r) => ({ variant_id: this.variantFor(r.colour, r.size)?.id, qty: 1, name: r.name, number: r.number }))
                    .filter((i) => i.variant_id);
            }

            const out = [];

            this.colourIds.forEach((colour) => {
                cfg.sizes.forEach((size) => {
                    const n = Number(this.qty[`${colour}:${size}`] ?? 0);
                    const v = this.variantFor(colour, size);

                    if (n > 0 && v) {
                        out.push({ variant_id: v.id, qty: n });
                    }
                });
            });

            return out;
        },

        get total() {
            return this.items.reduce((n, i) => n + i.qty, 0);
        },

        // Colours (quantity mode) ------------------------------------------------

        get addableColours() {
            return cfg.colours.filter((c) => ! this.colourIds.includes(c.id));
        },

        addColour(id) {
            if (id) {
                this.colourIds.push(Number(id));
            }
        },

        removeColour(id) {
            this.colourIds = this.colourIds.filter((c) => c !== id);
            cfg.sizes.forEach((size) => delete this.qty[`${id}:${size}`]);
            this.scheduleQuote();
        },

        colour(id) {
            return cfg.colours.find((c) => c.id === id);
        },

        // Roster -----------------------------------------------------------------

        addRows(n = 1) {
            for (let i = 0; i < n; i++) {
                const last = this.rows.at(-1);
                this.rows.push({ name: '', number: '', size: last?.size ?? cfg.sizes[0], colour: last?.colour ?? cfg.colourId });
            }
        },

        removeRow(i) {
            this.rows.splice(i, 1);
            this.previewIndex = Math.min(this.previewIndex, Math.max(this.rows.length - 1, 0));
        },

        get rowProblems() {
            const problems = [];
            const seen = {};

            this.rows.forEach((r, i) => {
                if (cfg.needsName && r.name.trim() === '') {
                    problems.push({ level: 'error', text: `Row ${i + 1} needs a name.` });
                }

                if (cfg.needsNumber && String(r.number).trim() === '') {
                    problems.push({ level: 'error', text: `Row ${i + 1} needs a number.` });
                }

                if (r.name.length > 30) {
                    problems.push({ level: 'error', text: `The name on row ${i + 1} is too long (30 characters at most).` });
                }

                if (String(r.number).length > 4) {
                    problems.push({ level: 'error', text: `The number on row ${i + 1} is too long (4 characters at most).` });
                }

                const n = String(r.number).trim();

                if (n !== '') {
                    (seen[n] ??= []).push(i + 1);
                }
            });

            Object.entries(seen).filter(([, rows]) => rows.length > 1).forEach(([n, rows]) => {
                problems.push({ level: 'warn', text: `Number ${n} is used on rows ${rows.join(', ')}. That is fine if it is intended.` });
            });

            return problems;
        },

        get hasErrors() {
            return this.mode === 'roster' && this.rowProblems.some((p) => p.level === 'error');
        },

        async importCsv(event) {
            const file = event.target.files?.[0];
            event.target.value = '';

            if (! file) {
                return;
            }

            const lines = (await file.text()).split(/\r?\n/).map((l) => l.trim()).filter(Boolean);
            const split = (l) => l.split(/[,;\t]/).map((c) => c.trim().replace(/^"|"$/g, ''));
            const header = split(lines[0]).map((h) => h.toLowerCase());
            const hasHeader = header.some((h) => ['name', 'number', 'size', 'colour', 'color'].includes(h));
            const col = (key, fallback) => {
                const at = header.findIndex((h) => key.includes(h));

                return hasHeader ? at : fallback;
            };

            const nameAt = col(['name'], 0);
            const numberAt = col(['number', 'no', 'no.'], 1);
            const sizeAt = col(['size'], 2);
            const colourAt = col(['colour', 'color'], 3);
            const rows = [];
            const skipped = [];

            lines.slice(hasHeader ? 1 : 0).forEach((line, n) => {
                const cells = split(line);
                const size = cfg.sizes.find((s) => s.toLowerCase() === (cells[sizeAt] ?? '').toLowerCase()) ?? cfg.sizes[0];
                const colourName = (cells[colourAt] ?? '').toLowerCase();
                const colour = cfg.colours.find((c) => c.name.toLowerCase() === colourName)?.id ?? cfg.colourId;

                if (sizeAt >= 0 && cells[sizeAt] && ! cfg.sizes.some((s) => s.toLowerCase() === cells[sizeAt].toLowerCase())) {
                    skipped.push(`row ${n + 1}: size "${cells[sizeAt]}" is not offered (used ${size})`);
                }

                rows.push({ name: cells[nameAt] ?? '', number: cells[numberAt] ?? '', size, colour });
            });

            this.rows = rows.slice(0, cfg.maxQty);
            this.previewIndex = 0;
            this.message = `Imported ${this.rows.length} rows.${skipped.length ? ' Check: '+skipped.slice(0, 3).join('; ')+(skipped.length > 3 ? '…' : '') : ''}`;
        },

        get previewRow() {
            return this.rows[this.previewIndex] ?? null;
        },

        get previewColour() {
            return this.mode === 'roster' ? (this.previewRow?.colour ?? cfg.colourId) : (this.colourIds[0] ?? cfg.colourId);
        },

        step(delta) {
            const n = this.rows.length;

            if (n) {
                this.previewIndex = (this.previewIndex + delta + n) % n;
            }
        },

        // Price ------------------------------------------------------------------

        scheduleQuote() {
            clearTimeout(timer);
            timer = setTimeout(() => this.fetchQuote(), 250);
        },

        async fetchQuote() {
            const items = this.items;

            if (! items.length) {
                this.quote = null;

                return;
            }

            controller?.abort();
            controller = new AbortController();
            this.quoting = true;

            try {
                const res = await fetch(cfg.urls.quote, {
                    method: 'POST',
                    signal: controller.signal,
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: JSON.stringify({ design_id: cfg.designId, items: items.map(({ variant_id, qty }) => ({ variant_id, qty })) }),
                });

                if (res.ok) {
                    this.quote = await res.json();
                }
            } catch (e) {
                if (e.name !== 'AbortError') {
                    this.quote = null;
                }
            } finally {
                this.quoting = false;
            }
        },

        money(minor) {
            const v = minor / 100;

            return `${cfg.symbol} ${minor % 100 === 0 ? v.toLocaleString('en-GH') : v.toLocaleString('en-GH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
        },

        submit() {
            this.message = '';

            if (! this.total) {
                this.message = 'Choose at least one item.';

                return;
            }

            if (this.hasErrors) {
                this.message = 'Fix the problems marked in red first.';

                return;
            }

            this.$refs.items.value = JSON.stringify(this.items);
            this.$refs.form.submit();
        },
    };
}
