<!DOCTYPE html>
<html>
<body>
<h2>Appointment Cancelled</h2>
<p>Hi {{ $recipient->name }},</p>
<p>The following appointment has been cancelled:</p>
<ul>
  <li><strong>Service:</strong> {{ $appointment->service->name }}</li>
  <li><strong>Staff:</strong> {{ $appointment->staff->name }}</li>
  <li><strong>Date:</strong> {{ $appointment->scheduled_date->format('d/m/Y H:i') }}</li>
</ul>
</body>
</html>
