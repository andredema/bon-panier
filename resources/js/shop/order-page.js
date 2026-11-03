// Page de commande (maquettes/commande.html) : catégories, filtres, détail / gros,
// cartes produit et panier. Le panier vit côté serveur : chaque geste l'envoie
// et l'écran affiche la réponse (lignes, précommandes, taxes, total).

const fr = new Intl.NumberFormat('fr-CA', { maximumFractionDigits: 3 });
const sansAccents = (s) => String(s ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
const echapper = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;

export default function orderPage(config) {
    return {
        categories: config.catalog.categories,
        products: config.catalog.products,
        wholesale: config.catalog.wholesale,
        cart: config.cart,
        slots: config.slots,
        t: config.texts,

        canal: 'detail',
        cat: null,
        dispo: [],
        cons: [],
        unite: [],
        q: '',
        tri: 'nom',
        choix: {},
        qte: {},
        menu: null,
        feuille: false,
        tiroir: config.openCheckout,
        avisTexte: '',
        avisVu: false,
        erreur: '',
        occupe: false,
        timer: null,

        init() {
            // Barres fixes et tiroirs : la page ne défile pas derrière
            this.$watch('tiroir', (v) => document.body.classList.toggle('tiroir-ouvert', v));
            this.$watch('feuille', (v) => document.body.classList.toggle('filtres-ouverts', v));
            if (this.tiroir) document.body.classList.add('tiroir-ouvert');
        },

        // ----- Catalogue -----
        formatsDe(p) {
            return p.formats.filter((f) => f.c === this.canal);
        },
        formatChoisi(p) {
            const fs = this.formatsDe(p);
            return fs[Math.min(this.choix[p.id + this.canal] ?? 0, fs.length - 1)];
        },
        cle(p) {
            const f = this.formatChoisi(p);
            return f ? `${p.id}-${f.id}` : '';
        },
        qteDe(p) {
            const f = this.formatChoisi(p);
            return this.qte[this.cle(p)] ?? Math.max(f.min_milli, f.step_milli);
        },
        qteLabel(milli, f) {
            const n = fr.format(milli / 1000);
            return f.by_weight ? `${n} ${f.unit}` : n;
        },
        pas(p, d) {
            const f = this.formatChoisi(p);
            const min = Math.max(f.min_milli, f.step_milli);
            this.qte[this.cle(p)] = Math.max(min, this.qteDe(p) + d * f.step_milli);
        },
        dansPanier(p) {
            return this.cart.lines.some((l) => l.product_id === p.id);
        },
        statut(p) {
            return p.status; // stock | preco | none
        },

        filtre(p, sauf = null) {
            if (!this.formatsDe(p).length) return false;
            if (sauf !== 'cat' && this.cat !== null && p.cat !== this.cat) return false;
            if (this.q && !sansAccents(`${p.name} ${p.brand ?? ''}`).includes(sansAccents(this.q))) return false;
            if (sauf !== 'dispo' && this.dispo.length && !this.dispo.includes(p.status === 'stock' ? 'stock' : 'preco')) return false;
            if (sauf !== 'cons' && this.cons.length && !this.cons.includes(p.frozen ? 'surgele' : 'sec')) return false;
            if (sauf !== 'unite' && this.unite.length && !this.formatsDe(p).some((f) => this.unite.includes(f.u))) return false;
            return true;
        },
        get liste() {
            const tri = {
                nom: (a, b) => a.name.localeCompare(b.name, 'fr'),
                prix: (a, b) => this.formatChoisi(a).price - this.formatChoisi(b).price,
                pop: (a, b) => a.pop - b.pop,
            }[this.tri];
            return this.products.filter((p) => this.filtre(p)).sort(tri);
        },
        compteCat(id) {
            return this.products.filter((p) => this.formatsDe(p).length && (id === null || p.cat === id)).length;
        },
        get catsTriees() {
            return [...this.categories].sort((a, b) => a.name.localeCompare(b.name, 'fr'));
        },
        choisirCat(id) {
            this.cat = id === null || this.cat === id ? null : id;
            this.$nextTick(() => {
                const t = document.getElementById('t-res');
                t.scrollIntoView({ behavior: 'smooth', block: 'start' });
                t.focus({ preventScroll: true });
            });
        },
        get titre() {
            if (this.cat !== null) return this.categories.find((c) => c.id === this.cat)?.name;
            return this.canal === 'gros' ? this.t.allWholesale : this.t.all;
        },
        nbProduits(n) {
            return n > 1 ? this.t.products.replace(':n', n) : this.t.product.replace(':n', n);
        },
        options(groupe) {
            const base = this.products.filter((p) => this.filtre(p, groupe));
            const n = (fn) => base.filter(fn).length;
            return {
                dispo: [['stock', this.t.inStock, n((p) => p.status === 'stock')], ['preco', this.t.preorder, n((p) => p.status !== 'stock')]],
                cons: [['surgele', this.t.frozen, n((p) => p.frozen)], ['sec', this.t.dry, n((p) => !p.frozen)]],
                unite: Object.entries(this.t.units).map(([code, label]) => [code, label, n((p) => this.formatsDe(p).some((f) => f.u === code))]),
            }[groupe];
        },
        basculer(groupe, valeur) {
            const s = this[groupe];
            const i = s.indexOf(valeur);
            i >= 0 ? s.splice(i, 1) : s.push(valeur);
        },
        get puces() {
            const lib = Object.fromEntries([...this.options('dispo'), ...this.options('cons')].map(([v, l]) => [v, l]));
            return [
                ...this.dispo.map((v) => ['dispo', v, lib[v]]),
                ...this.cons.map((v) => ['cons', v, lib[v]]),
                ...this.unite.map((v) => ['unite', v, this.t.soldIn.replace(':unit', this.t.units[v] ?? v)]),
            ];
        },
        get nbFiltres() {
            return this.dispo.length + this.cons.length + this.unite.length;
        },
        effacer() {
            this.dispo = [];
            this.cons = [];
            this.unite = [];
            this.cat = null;
        },
        changerCanal(c) {
            this.canal = c;
        },

        // ----- Panier (serveur) -----
        async envoyer(method, url, body) {
            this.occupe = true;
            this.erreur = '';
            try {
                const res = await fetch(url, {
                    method,
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
                    body: body ? JSON.stringify(body) : undefined,
                });
                const data = await res.json();
                this.cart = data.cart;
                this.slots = data.slots;
                if (data.error) this.erreur = data.error;
                return res.ok;
            } catch {
                this.erreur = this.t.network;
                return false;
            } finally {
                this.occupe = false;
            }
        },
        async ajouter(p) {
            const f = this.formatChoisi(p);
            const ok = await this.envoyer('POST', config.urls.add, { format_id: f.id, qty: String(this.qteDe(p) / 1000) });
            // L'avis est en HTML (total en gras) : les textes variables sont échappés
            this.avis(ok
                ? (p.status === 'stock' ? this.t.added : this.t.preordered).replace(':name', echapper(p.name)).replace(':total', echapper(this.cart.labels.total))
                : echapper(this.erreur));
        },
        async changerLigne(l, d) {
            const qty = Math.round(Number(l.qty) * 1000) + d * Math.round(Number(l.step) * 1000);
            if (qty < Math.round(Number(l.min) * 1000)) return this.retirer(l);
            await this.envoyer('PATCH', config.urls.line.replace('__ID__', l.format_id), { qty: String(qty / 1000) });
            if (this.erreur) this.avis(echapper(this.erreur));
        },
        async retirer(l) {
            await this.envoyer('DELETE', config.urls.line.replace('__ID__', l.format_id));
        },
        get lignesDispo() {
            return this.cart.lines.filter((l) => !l.preorder);
        },
        get lignesPreco() {
            return this.cart.lines.filter((l) => l.preorder);
        },
        avis(texte) {
            this.avisTexte = texte;
            this.avisVu = true;
            clearTimeout(this.timer);
            this.timer = setTimeout(() => (this.avisVu = false), 2200);
        },

        // ----- Tiroir de finalisation -----
        ouvrir() {
            this.menu = null;
            this.tiroir = true;
            this.$nextTick(() => document.getElementById('fermer')?.focus());
        },
        fermer() {
            this.tiroir = false;
            this.feuille = false;
        },
        ouvrirFiltres() {
            this.feuille = true;
            this.$nextTick(() => document.getElementById('fermer-filtres')?.focus());
        },
    };
}
