// Commande au comptoir : recherche de produits, lignes (format + quantité) et
// aperçu des totaux calculé par le serveur avec les mêmes règles que la page en ligne.

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;

export default function counterOrder(config) {
    return {
        lines: config.lines,
        q: '',
        results: [],
        summary: null,
        errors: [],
        timer: null,

        search() {
            clearTimeout(this.timer);
            const q = this.q.trim();
            if (!q) {
                this.results = [];
                return;
            }
            this.timer = setTimeout(async () => {
                const res = await fetch(`${config.searchUrl}?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' } });
                this.results = res.ok ? await res.json() : [];
            }, 200);
        },
        add(product) {
            const f = product.formats[0];
            this.lines.push({ key: crypto.randomUUID(), name: product.name, available: product.available, formats: product.formats, format_id: f.id, qty: String(Number(f.min)) });
            this.q = '';
            this.results = [];
            this.refresh();
        },
        remove(i) {
            this.lines.splice(i, 1);
            this.refresh();
        },
        changeFormat(line) {
            const f = line.formats.find((x) => String(x.id) === String(line.format_id));
            if (f) line.qty = String(Number(f.min));
            this.refresh();
        },
        async refresh() {
            if (!this.lines.length) {
                this.summary = null;
                this.errors = [];
                return;
            }
            const res = await fetch(config.previewUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({ lines: this.lines.map((l) => ({ format_id: l.format_id, qty: String(l.qty).replace(',', '.') })) }),
            });
            const data = await res.json();
            this.errors = data.errors ?? [];
            if (data.cart) this.summary = data.cart;
        },
        status(line) {
            const l = this.summary?.lines.find((x) => String(x.format_id) === String(line.format_id));
            if (!l) return '';
            if (l.unavailable) return config.texts.unavailable;
            if (l.preorder || l.partial_preorder) return config.texts.preorder.replace(':date', l.expected_label ?? '—');
            return '';
        },
    };
}
