<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\AccountingPack;
use App\Services\Reports\Period;
use App\Services\Reports\ReportExports;
use App\Services\Reports\ReportPdf;
use App\Services\Reports\Reports;
use App\Support\Csv;
use App\Support\Format;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Rapports et documents comptables (maquettes/rapport-mensuel.html) ; réservé au gérant. */
class ReportController extends Controller
{
    private const JOURNAL_LIMIT = 50;

    public function index(Request $request, Reports $reports): View
    {
        $p = $this->period($request);
        $invoices = $reports->invoices($p);
        $credits = $reports->creditNotes($p);

        return view('admin.reports.index', [
            'period' => $p,
            'options' => Period::options(),
            'summary' => $reports->summary($p),
            'taxes' => $reports->taxes($p),
            'payments' => $reports->paymentsByMethod($p),
            'channels' => $reports->byChannel($p),
            'categories' => $reports->byCategory($p),
            'top' => $reports->topProducts($p),
            'receivables' => $reports->receivables(),
            'inventory' => $reports->inventory(),
            'journal' => $invoices->take(self::JOURNAL_LIMIT),
            'journalCount' => $invoices->count() + $credits->count(),
            'credits' => $credits,
            'adjustments' => $reports->adjustments($p),
            'accountant' => Settings::get('accounting.email'),
            'query' => $this->query($request),
            'limit' => self::JOURNAL_LIMIT,
        ]);
    }

    public function csv(Request $request, string $type, ReportExports $exports): StreamedResponse
    {
        abort_unless(in_array($type, ReportExports::TYPES, true), 404);
        [$name, $headers, $rows] = $exports->build($type, $this->period($request));

        return Csv::download($name, $headers, $rows);
    }

    public function pdf(Request $request, string $type, ReportPdf $pdf): Response
    {
        $p = $this->period($request);

        [$content, $name] = match ($type) {
            'mensuel' => [$pdf->monthly($p), 'rapport_'.$p->slug()],
            'taxes' => [$pdf->render($p, ['taxes'], __('reports.taxes.title')), 'rapport-de-taxes_'.$p->slug()],
            'comptes-clients' => [$pdf->render($p, ['receivables'], __('reports.receivables.title')), 'comptes-clients_'.today()->toDateString()],
            'inventaire' => [$pdf->render($p, ['inventory'], __('reports.inventory.title', ['date' => Format::date(today())])), 'inventaire_'.today()->toDateString()],
            default => abort(404),
        };

        return response($content, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$name.'.pdf"']);
    }

    public function send(Request $request): RedirectResponse
    {
        $email = Settings::get('accounting.email');
        if (! $email) {
            return back()->withErrors(['accountant' => __('reports.exports.no_accountant')]);
        }

        $p = $this->period($request);
        Mail::to($email)->queue(new AccountingPack($p->key, $request->input('du'), $request->input('au')));

        return back()->with('status', __('reports.exports.sent', ['email' => $email]));
    }

    private function period(Request $request): Period
    {
        return Period::fromRequest($request->input('periode'), $request->input('du'), $request->input('au'));
    }

    /** Paramètres de période à reporter sur les liens d'export. */
    private function query(Request $request): array
    {
        return array_filter($request->only(['periode', 'du', 'au']));
    }
}
