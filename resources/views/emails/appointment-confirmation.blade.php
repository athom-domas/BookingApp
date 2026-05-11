<!DOCTYPE html>
<html>
<body>
<h2>Appointment Confirmed</h2>
<p>Hi {{ $appointment->user->name }},</p>
<p>Your appointment has been confirmed:</p>
<ul>
  <li><strong>Service:</strong> {{ $appointment->service->name }}</li>
  <li><strong>Staff:</strong> {{ $appointment->staff->name }}</li>
  <li><strong>Date:</strong> {{ $appointment->scheduled_date->format('d/m/Y H:i') }}</li>
  <li><strong>Price:</strong> €{{ number_format($appointment->final_price, 2) }}</li>
</ul>
</body>
</html>
