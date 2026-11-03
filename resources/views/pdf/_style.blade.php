{{-- Styles des PDF (dompdf : CSS 2.1, mise en page en tableaux) — couleurs de maquettes/facture.html --}}
<style>
  @page { margin: 36px 44px; }
  * { font-family: 'DejaVu Sans', sans-serif; }
  body { font-size: 10.5px; color: #1E1B16; }
  h1, h2, h3, p { margin: 0; }
  .tete { width: 100%; border-bottom: 3px solid #1F4D2E; padding-bottom: 14px; }
  .tete td { vertical-align: top; }
  .logo { width: 74px; height: 74px; }
  .emetteur h1 { font-size: 16px; color: #1F4D2E; }
  .emetteur p { color: #4A4033; line-height: 1.45; margin-top: 3px; }
  .doc { text-align: right; }
  .doc h2 { font-size: 22px; letter-spacing: 1px; }
  .doc table { margin-left: auto; margin-top: 6px; }
  .doc td { padding: 1px 0 1px 10px; }
  .doc .l { color: #6B5F4F; }
  .doc .v { font-weight: bold; }
  .parties { width: 100%; margin: 16px 0; }
  .parties td { vertical-align: top; width: 50%; }
  .parties h3 { font-size: 8.5px; letter-spacing: 1.5px; text-transform: uppercase; color: #6B5F4F; margin-bottom: 4px; }
  .parties p { line-height: 1.5; }
  .lignes { width: 100%; border-collapse: collapse; }
  .lignes th { background: #F7F2E8; text-align: left; font-size: 8.5px; letter-spacing: 1px; text-transform: uppercase; color: #4A4033; padding: 7px 6px; }
  .lignes td { padding: 8px 6px; border-bottom: 1px solid #E6DCC8; vertical-align: top; }
  .lignes small { color: #6B5F4F; display: block; font-size: 9px; }
  .d { text-align: right; }
  .code { font-size: 8.5px; font-weight: bold; padding: 1px 5px; }
  .code-d { background: #E4EDE6; color: #1F4D2E; }
  .code-t { background: #F6E8CF; color: #7A4F0E; }
  .legende { font-size: 9px; color: #6B5F4F; margin-top: 6px; }
  .bas { width: 100%; margin-top: 16px; }
  .bas td { vertical-align: top; }
  .paiement { background: #F6E8CF; padding: 12px; line-height: 1.5; }
  .paiement h3 { font-size: 10.5px; margin-bottom: 4px; }
  .statut { display: inline-block; margin-top: 8px; border: 2px solid #A8432A; color: #A8432A; font-weight: bold; letter-spacing: 1px; padding: 3px 8px; font-size: 9px; }
  .statut-ok { border-color: #1F4D2E; color: #1F4D2E; }
  .totaux { width: 100%; border-collapse: collapse; }
  .totaux td { padding: 4px 0; }
  .totaux .grand td { border-top: 2px solid #1E1B16; padding-top: 7px; font-size: 15px; font-weight: bold; }
  .pied { margin-top: 28px; padding-top: 10px; border-top: 1px solid #E6DCC8; font-size: 9px; color: #6B5F4F; width: 100%; }
</style>
