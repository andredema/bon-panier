// Fiche produit : lignes de formats, prix par unité de stock et aperçu de la carte client.
// Les calculs ici servent seulement à l'affichage ; le serveur refait tout en entiers.

const money = new Intl.NumberFormat('fr-CA', { style: 'currency', currency: 'CAD' });
const num = new Intl.NumberFormat('fr-CA', { maximumFractionDigits: 3 });

// « 1 284,50 » → 1284.5 ; NaN si la saisie n'est pas un nombre
const parse = (v) => {
    const s = String(v ?? '').replace(/[\s  $]/g, '').replace(',', '.');
    return /^-?\d+(\.\d+)?$/.test(s) ? Number(s) : NaN;
};

export default function productForm(config) {
    return {
        rows: config.rows.map((r) => ({ ...blank(config), ...r, key: crypto.randomUUID() })),
        units: config.units,
        categories: config.categories,
        stockUnitId: String(config.stockUnitId ?? ''),
        categoryId: String(config.categoryId ?? ''),
        name: config.name,
        errors: config.errors,
        preview: 'detail',
        photoUrl: config.photoUrl,
        removePhoto: false,

        unit(id) {
            return this.units.find((u) => String(u.id) === String(id));
        },
        get stockUnit() {
            return this.unit(this.stockUnitId);
        },
        get category() {
            return this.categories.find((c) => String(c.id) === this.categoryId) ?? this.categories[0];
        },
        get measureUnits() {
            return this.units.filter((u) => u.kind !== 'count');
        },
        rowsFor(channel) {
            return this.rows.map((row, i) => ({ row, i })).filter(({ row }) => row.channel === channel);
        },

        add(channel) {
            const u = this.stockUnit ?? this.units[0];
            this.rows.push({ ...blank(config), channel, unit_id: u?.id ?? '', key: crypto.randomUUID() });
            this.$nextTick(() => this.$el.querySelector(`[data-key="${this.rows.at(-1).key}"] input[type=text]`)?.focus());
        },
        remove(i) {
            this.rows.splice(i, 1);
            this.errors = {}; // les indices ont changé : les erreurs du serveur ne correspondent plus
        },
        error(i, field) {
            return this.errors[`formats.${i}.${field}`]?.[0] ?? '';
        },

        canWeigh(row) {
            const sale = this.unit(row.unit_id);
            const stock = this.stockUnit;
            return sale && stock && sale.kind !== 'count' && sale.kind === stock.kind;
        },
        // Ce que retire du stock une quantité de 1, dans l'unité de stock
        content(row) {
            if (row.is_by_weight) {
                const sale = this.unit(row.unit_id);
                return this.canWeigh(row) ? sale.base_factor / this.stockUnit.base_factor : NaN;
            }
            return parse(row.content_qty);
        },
        perStockLabel() {
            const s = this.stockUnit;
            if (!s) return '';
            return s.kind === 'mass' ? 'kg' : s.kind === 'volume' ? 'L' : s.short_label;
        },
        // Prix par kg ou par L si le stock se mesure, sinon par unité de stock
        perStock(row) {
            const price = parse(row.price);
            const content = this.content(row);
            const s = this.stockUnit;
            if (!s || !(price > 0) || !(content > 0)) return '—';
            const qty = s.kind === 'count' ? content : (content * s.base_factor) / 1e6;
            return money.format(price / qty);
        },
        showNet(row) {
            return this.stockUnit?.kind === 'count' && !row.is_by_weight;
        },
        visibleFor(channel) {
            return this.rows.filter((r) => r.channel === channel && r.is_visible && r.label);
        },
        priceLabel(row) {
            const p = parse(row.price);
            if (!(p >= 0)) return '—';
            return money.format(p) + (row.is_by_weight ? '/' + (this.unit(row.unit_id)?.short_label ?? '') : '');
        },
        minLabel(row) {
            const min = parse(row.min_qty);
            const step = parse(row.step);
            return min > step ? `min. ${num.format(min)}` : '';
        },
        onUnitChange(row) {
            if (row.is_by_weight && !this.canWeigh(row)) row.is_by_weight = false;
        },
        onWeighChange(row) {
            const sale = this.unit(row.unit_id);
            if (row.is_by_weight && sale) {
                row.step = num.format(Number(sale.default_step));
                row.min_qty = row.step;
            } else if (!row.is_by_weight) {
                row.step = '1';
                row.min_qty = '1';
            }
        },
        pickPhoto(event) {
            const file = event.target.files[0];
            if (file) {
                this.photoUrl = URL.createObjectURL(file);
                this.removePhoto = false;
            }
        },
        clearPhoto() {
            this.photoUrl = null;
            this.removePhoto = true;
            this.$refs.photo.value = '';
        },
    };
}

function blank(config) {
    return {
        id: '',
        channel: 'detail',
        unit_id: config.stockUnitId ?? '',
        label: '',
        is_by_weight: false,
        content_qty: '1',
        net_qty: '',
        net_unit_id: config.units.find((u) => u.code === 'kg')?.id ?? '',
        price: '',
        min_qty: '1',
        step: '1',
        is_visible: true,
    };
}
