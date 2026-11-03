// Entrée en stock : recherche (lecteur de codes-barres compris), lignes reçues,
// conversion en unité de stock et résumé. Calculs d'affichage seulement :
// le serveur refait la conversion en entiers et refuse ce qui ne tombe pas juste.

const money = new Intl.NumberFormat('fr-CA', { style: 'currency', currency: 'CAD' });
const num = new Intl.NumberFormat('fr-CA', { maximumFractionDigits: 3 });

const parse = (v) => {
    const s = String(v ?? '').replace(/[\s  $]/g, '').replace(',', '.');
    return /^\d+(\.\d+)?$/.test(s) ? Number(s) : NaN;
};

export default function stockReceipt(config) {
    return {
        lines: config.lines.map((l) => ({ ...l, key: crypto.randomUUID() })),
        soon: config.soon,
        errors: config.errors,
        q: '',
        results: [],
        searched: false,
        timer: null,

        search() {
            clearTimeout(this.timer);
            const q = this.q.trim();
            if (!q) {
                this.results = [];
                this.searched = false;
                return;
            }
            this.timer = setTimeout(() => this.fetch(q), 200);
        },
        async fetch(q) {
            const res = await fetch(`${config.searchUrl}?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } });
            this.results = res.ok ? await res.json() : [];
            this.searched = true;
            return this.results;
        },
        // Entrée : le lecteur de codes-barres tape le code puis « Entrée »
        async enter() {
            clearTimeout(this.timer);
            const q = this.q.trim();
            if (!q) return;
            const results = await this.fetch(q);
            const exact = results.find((p) => p.barcode === q);
            if (exact || results.length === 1) this.add(exact ?? results[0]);
        },
        add(product) {
            const first = product.sources[0];
            this.lines.push({
                key: crypto.randomUUID(),
                product,
                source: first.value,
                received_qty: '1',
                other_label: '',
                other_content: '',
                lot_number: '',
                best_before: '',
                unit_cost: '',
            });
            this.soon = this.soon.filter((p) => p.id !== product.id);
            this.q = '';
            this.results = [];
            this.searched = false;
            this.errors = {};
            this.$nextTick(() => document.getElementById(`q-${this.lines.at(-1).key}`)?.focus());
        },
        remove(i) {
            this.lines.splice(i, 1);
            this.errors = {};
        },
        error(i, field) {
            return this.errors[`lines.${i}.${field}`]?.[0] ?? '';
        },

        option(line) {
            return line.product.sources.find((s) => s.value === line.source) ?? line.product.sources[0];
        },
        allowsDecimals(line) {
            return this.option(line).decimals;
        },
        step(line, delta) {
            const q = parse(line.received_qty);
            const next = Math.max(1, (Number.isNaN(q) ? 0 : Math.floor(q)) + delta);
            line.received_qty = String(next);
        },
        content(line) {
            return line.source === 'other' ? parse(line.other_content) : parse(this.option(line).content);
        },
        added(line) {
            const q = parse(line.received_qty);
            const c = this.content(line);
            return q > 0 && c > 0 ? q * c : NaN;
        },
        // Stock du produit avant cette ligne, en comptant les lignes précédentes du même produit
        before(line, i) {
            let milli = line.product.on_hand_milli;
            this.lines.slice(0, i).forEach((l) => {
                if (l.product.id === line.product.id) milli += (this.added(l) || 0) * 1000;
            });
            return milli / 1000;
        },
        fmt(n) {
            return Number.isNaN(n) ? '—' : num.format(n);
        },

        get packages() {
            return this.lines.reduce((s, l) => s + (parse(l.received_qty) || 0), 0);
        },
        get cost() {
            return this.lines.reduce((s, l) => s + (parse(l.unit_cost) || 0) * (parse(l.received_qty) || 0), 0);
        },
        get costLabel() {
            return money.format(this.cost);
        },
    };
}
