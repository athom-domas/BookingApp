<!DOCTYPE html>
<html>
<body>
@include('emails.partials.header')
<h2>Appointment Cancelled</h2>
<p>Hi {{ $recipient->name }},</p>
<p>The following appointment has been cancelled:</p>
<ul>
  <li><strong>Servizi:</strong> {{ $appointment->services_label }}</li>
  <li><strong>Staff:</strong> {{ $appointment->staff->name }}</li>
  <li><strong>Date:</strong> {{ $appointment->scheduled_date->format('d/m/Y H:i') }}</li>
</ul>
@include('emails.partials.salon-footer')
</body>
</html>
