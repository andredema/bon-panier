<?php

namespace App\Services\Reports;

use App\Support\Settings;
use Barryvdh\DomPDF\Facade\Pdf;

/** Rapports en PDF (dompdf), avec les sections voulues. */
final class ReportPdf
{
    public function __construct(private Reports $reports) {}

    /** @param  list<string>  $sections  kpis, taxes, payments, channels, categories, top, receivables, inventory */
    public function render(Period $p, array $sections, string $title): string
    {
        $data = ['period' => $p, 'sections' => $sections, 'title' => $title, 'shopName' => Settings::get('shop.name')];

        // Données calculées seulement pour les sections demandées
        $builders = [
            'kpis' => fn () => $this->reports->summary($p),
            'taxes' => fn () => $this->reports->taxes($p),
            'payments' => fn () => $this->reports->paymentsByMethod($p),
            'channels' => fn () => $this->reports->byChannel($p),
            'categories' => fn () => $this->reports->byCategory($p),
            'top' => fn () => $this->reports->topProducts($p),
            'receivables' => fn () => $this->reports->receivables(),
            'inventory' => fn () => $this->reports->inventory(),
        ];
        foreach ($sections as $section) {
            $data[$section] = $builders[$section]();
        }

        return Pdf::loadView('pdf.report', $data)->setPaper('letter')
            ->setOption(['defaultFont' => 'DejaVu Sans', 'isRemoteEnabled' => false, 'isFontSubsettingEnabled' => true])
            ->output();
    }

    public function monthly(Period $p): string
    {
        return $this->render($p, ['kpis', 'taxes', 'payments', 'channels', 'categories', 'top', 'receivables'], __('reports.monthly', ['period' => mb_strtolower($p->label)]));
    }
}
