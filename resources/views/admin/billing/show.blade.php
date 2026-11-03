@extends('layouts.admin')

@php
    use App\Support\Format;
    $balance = $invoice->balanceCents();
    $status = $invoice->status();
    $credited = $invoice->creditedCents();
    $paid = $invoice->paidCents();
    $creditable = $invoice->lines->sum(fn ($l) => $l->creditableCents()) + $invoice->delivery_fee_cents - $invoice->creditNotes->sum('delivery_cents');
@endphp

@section('title', __('billing.show.title', ['number' => $invoice->number]))

@section('content')
  <div class="barre">
    <div>
      <p class="fil"><a href="{{ route('admin.invoices.index') }}">{{ __('billing.title') }}</a></p>
      <h1>{{ __('billing.show.title', ['number' => $invoice->number]) }}
        <span class="etiquette {{ ['a_payer' => 'e-annulee', 'partielle' => 'e-confirmee', 'payee' => 'e-prete'][$status] }}">{{ __('billing.status.'.$status) }}</span></h1>
      <p>
        {{ __('billing.show.issued', ['date' => Format::date($invoice->issued_at)]) }}
        @if ($invoice->due_on) · {{ __('billing.show.due', ['date' => Format::date($invoice->due_on)]) }}@endif
        · <a href="{{ route('admin.orders.show', $invoice->order) }}">{{ __('billing.show.order', ['number' => $invoice->order->number]) }}</a>
      </p>
    </div>
    <div class="actions">
      <a class="btn btn-secondaire" href="{{ route('admin.invoices.pdf', $invoice) }}" target="_blank" rel="noopener">{{ __('billing.show.pdf') }}</a>
      @if ($invoice->customer_snapshot['email'])
        <form method="POST" action="{{ route('admin.invoices.email', $invoice) }}">@csrf<button class="btn btn-primaire" type="submit">{{ __('billing.show.email') }}</button></form>
      @endif
    </div>
  </div>

  <p class="alerte alerte-info">{{ __('billing.show.immutable') }}
    · {{ $invoice->customer_snapshot['email'] ? ($invoice->emailed_at ? __('billing.show.emailed', ['date' => Format::date($invoice->emailed_at)]) : __('billing.show.not_emailed')) : __('billing.show.no_email') }}</p>

  @if ($errors->any())
    <div class="alerte alerte-erreur" role="alert">{{ $errors->first() }}</div>
  @endif

  <div class="rangee-fiche">
    <section class="bloc carte" aria-labelledby="t-lignes">
      <h2 id="t-lignes">{{ __('billing.show.lines') }}</h2>
      <table class="recap-admin">
        <tbody>
          @foreach ($invoice->lines as $l)
            <tr>
              <td><b>{{ $l->product_name }}</b>
                <small>{{ $l->format_label }} · {{ Format::quantity($l->qty) }}{{ $l->qty_unit ? ' '.$l->qty_unit : '' }} × @money($l->unit_price_cents) · {{ $l->is_taxable ? 'T' : 'D' }}@if ($l->lot_numbers) · {{ __('billing.pdf.lot') }} {{ $l->lot_numbers }}@endif</small></td>
              <td class="num">@money($l->line_total_cents)</td>
            </tr>
          @endforeach
          <tr><td>{{ __('billing.pdf.subtotal') }}</td><td class="num">@money($invoice->subtotal_cents)</td></tr>
          @if ($invoice->delivery_fee_cents)<tr><td>{{ __('billing.pdf.delivery_fee') }}</td><td class="num">@money($invoice->delivery_fee_cents)</td></tr>@endif
          <tr><td>TPS</td><td class="num">@money($invoice->tax_tps_cents)</td></tr>
          <tr><td>TVQ</td><td class="num">@money($invoice->tax_tvq_cents)</td></tr>
          <tr class="total"><td>{{ __('billing.pdf.total') }}</td><td class="num">@money($invoice->total_cents)</td></tr>
          @if ($credited)<tr><td>{{ __('billing.show.credited') }}</td><td class="num">− @money($credited)</td></tr>@endif
          @if ($paid)<tr><td>{{ __('billing.show.paid') }}</td><td class="num">− @money($paid)</td></tr>@endif
          <tr class="total"><td>{{ $balance < 0 ? __('billing.show.refund') : __('billing.show.balance') }}</td><td class="num">@money(abs($balance))</td></tr>
        </tbody>
      </table>
    </section>

    <div class="col">
      <section class="bloc carte" aria-labelledby="t-paie">
        <h2 id="t-paie">{{ __('billing.show.payments') }}</h2>
        @include('admin.billing._payments', ['payments' => $invoice->payments])
        @if ($balance > 0)
          <h3>{{ __('billing.payment.title') }}</h3>
          @include('admin.billing._payment-form', ['order' => $invoice->order, 'due' => $balance])
        @endif
      </section>

      <section class="bloc carte" aria-labelledby="t-nc">
        <div class="bloc-tete">
          <h2 id="t-nc">{{ __('billing.show.credit_notes') }}</h2>
          @if ($creditable > 0)
            <a class="btn btn-secondaire mini" href="{{ route('admin.invoices.credit', $invoice) }}">{{ __('billing.show.new_credit') }}</a>
          @endif
        </div>
        @forelse ($invoice->creditNotes as $n)
          <p class="note">
            <a href="{{ route('admin.credit-notes.pdf', $n) }}" target="_blank" rel="noopener"><b>{{ $n->number }}</b></a>
            · {{ Format::date($n->issued_at) }} · <span class="num">@money($n->total_cents)</span>
            <br>{{ $n->reason }}@if ($n->user) · {{ $n->user->name }}@endif
          </p>
        @empty
          <p class="note">{{ __('billing.show.no_credit') }}</p>
        @endforelse
      </section>
    </div>
  </div>
@endsection
