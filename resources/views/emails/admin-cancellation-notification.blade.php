@extends('emails.layouts.base')

@section('title')Prenotazione cancellata @endsection
@section('badge')Admin @endsection
@section('skip-greeting') @endsection

@section('body')
    <p>Il cliente <strong>{{ $appointment->user->name }}</strong> ha cancellato il seguente appuntamento.</p>

    <div class="detail-card">
        <div class="detail-row">
            <span class="detail-label">Cliente</span>
            <span class="detail-value">{{ $appointment->user->name }}<br><span style="font-weight:400; color:#6b7280; font-size:0.825rem;">{{ $appointment->user->email }}</span></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Servizi</span>
            <span class="detail-value">{{ $appointment->services_label }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Operatore</span>
            <span class="detail-value">{{ $appointment->staff->name }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Data</span>
            <span class="detail-value">{{ $appointment->scheduled_date->format('d/m/Y') }} alle {{ $appointment->scheduled_date->format('H:i') }}</span>
        </div>
        @if($payment)
        <div class="detail-row">
            <span class="detail-label">Pagamento</span>
            <span class="detail-value">
                €{{ number_format($payment->amount, 2, ',', '.') }}
                — {{ match($payment->payment_method) {
                    'stripe' => 'Stripe',
                    'cash'   => 'Contanti',
                    'pos'    => 'POS',
                    default  => $payment->payment_method,
                } }}
                @if($payment->status === 'refunded')
                    <br><span style="color:#16a34a; font-weight:400;">✓ Rimborsato automaticamente</span>
                @else
                    <br><span style="color:#d97706; font-weight:400;">⚠ Da rimborsare manualmente</span>
                @endif
            </span>
        </div>
        @endif
    </div>
@endsection

@section('actions')
    <a href="{{ url('/admin/appointments/' . $appointment->id . '/edit') }}" class="btn" style="background-color: #2563eb; color: #ffffff;">Apri prenotazione</a>
@endsection

@section('footer-note')
    @if($payment && $payment->status !== 'refunded')
        Accedi al pannello per gestire il rimborso.
    @else
        Accedi al pannello per consultare i dettagli.
    @endif
@endsection
