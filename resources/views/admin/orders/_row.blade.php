{{-- Ligne de commande (liste et tableau de bord) : un seul bouton d'étape suivante --}}
@php
    use App\Support\Format;
    $slot = $order->slot_start->isToday() ? __('orders.today_short', ['time' => Format::time($order->slot_start)])
        : ($order->slot_start->isTomorrow() ? __('orders.tomorrow_short', ['time' => Format::time($order->slot_start)])
        : Format::shortDate($order->slot_start).', '.Format::time($order->slot_start));
    $pending = $order->items_pending_count ?? 0;
@endphp
<tr>
  <td data-lib="{{ __('orders.cols.number') }}"><a href="{{ route('admin.orders.show', $order) }}">{{ $order->number }}</a></td>
  <td data-lib="{{ __('orders.cols.customer') }}">
    <span>{{ $order->customer->name }}
      @if (! empty($compact) && $order->channel->value === 'gros')<span class="tag-comptoir">{{ __('orders.filters.wholesale') }}</span>@endif
      @if ($pending)<span class="tag-preco">{{ __('orders.preorder_tag') }}</span>@endif
      @if ($order->source === \App\Models\Order::SOURCE_COUNTER)<span class="tag-comptoir">{{ __('orders.counter_tag') }}</span>@endif
    </span>
  </td>
  @if (empty($compact))
    <td data-lib="{{ __('orders.cols.channel') }}"><span class="etiquette {{ $order->channel->value === 'gros' ? 'e-gros' : 'e-detail' }}">{{ $order->channel->value === 'gros' ? __('orders.filters.wholesale') : __('orders.filters.retail') }}</span></td>
  @endif
  <td data-lib="{{ __('orders.cols.slot') }}"><span>{{ $slot }}<small>{{ $order->fulfilment === \App\Models\Order::DELIVERY ? __('orders.filters.delivery') : __('orders.filters.pickup') }}</small></span></td>
  <td data-lib="{{ __('orders.cols.total') }}" class="num droite-large">@money($order->total_cents)</td>
  <td data-lib="{{ __('orders.cols.status') }}"><span class="etiquette {{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span></td>
  <td class="droite">
    @if ($order->status->next())
      @if ($pending && $order->status->next()->needsPreordersServed())
        <span class="note">{{ __('orders.waiting_preorder') }}</span>
      @else
        <form method="POST" action="{{ route('admin.orders.advance', $order) }}">
          @csrf
          <button class="btn btn-primaire mini suivant" type="submit">{{ $order->status->nextActionLabel() }}<span class="sr"> {{ $order->number }}</span></button>
        </form>
      @endif
    @endif
  </td>
</tr>
