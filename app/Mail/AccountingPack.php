<?php

namespace App\Mail;

use App\Services\Reports\Period;
use App\Services\Reports\ReportExports;
use App\Services\Reports\ReportPdf;
use App\Support\Csv;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/** « Tout envoyer au comptable » : les CSV et le rapport PDF de la période, produits à l'envoi. */
class AccountingPack extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public string $periodKey, public ?string $dateFrom = null, public ?string $dateTo = null) {}

    private function period(): Period
    {
        return Period::fromRequest($this->periodKey, $this->dateFrom, $this->dateTo);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('reports.mail.subject', ['period' => $this->period()->label]));
    }

    public function content(): Content
    {
        return new Content(htmlString: '<p>'.e(__('reports.mail.body', ['span' => $this->period()->span()])).'</p>');
    }

    /** @return list<Attachment> */
    public function attachments(): array
    {
        $p = $this->period();
        $exports = app(ReportExports::class);
        $files = array_map(function (string $type) use ($exports, $p) {
            [$name, $headers, $rows] = $exports->build($type, $p);

            return Attachment::fromData(fn () => Csv::content($headers, $rows), $name)->withMime('text/csv');
        }, ReportExports::TYPES);

        $files[] = Attachment::fromData(fn () => app(ReportPdf::class)->monthly($p), 'rapport_'.$p->slug().'.pdf')->withMime('application/pdf');

        return $files;
    }
}
