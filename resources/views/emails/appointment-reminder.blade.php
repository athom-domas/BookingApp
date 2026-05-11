<!DOCTYPE html>
<html>
<body>
<h2>Reminder: {{ $appointment->service->name }}</h2>
<p>Hi {{ $appointment->user->name }},</p>
<p>This is a reminder that your appointment is tomorrow:</p>
<ul>
  <li><strong>Service:</strong> {{ $appointment->service->name }}</li>
  <li><strong>Staff:</strong> {{ $appointment->staff->name }}</li>
  <li><strong>Date:</strong> {{ $appointment->scheduled_date->format('d/m/Y H:i') }}</li>
</ul>
</body>
</html>
