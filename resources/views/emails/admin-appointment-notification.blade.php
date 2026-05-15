<!DOCTYPE html>
<html>
<body>
<h2>Nuova prenotazione ricevuta</h2>
<ul>
  <li><strong>Cliente:</strong> {{ $appointment->user->name }} ({{ $appointment->user->email }})</li>
  <li><strong>Servizio:</strong> {{ $appointment->service->name }}</li>
  <li><strong>Operatore:</strong> {{ $appointment->staff->name }}</li>
  <li><strong>Data:</strong> {{ $appointment->scheduled_date->format('d/m/Y H:i') }}</li>
  <li><strong>Prezzo:</strong> €{{ number_format($appointment->final_price, 2) }}</li>
  @if($appointment->notes)
  <li><strong>Note:</strong> {{ $appointment->notes }}</li>
  @endif
</ul>
</body>
</html>
