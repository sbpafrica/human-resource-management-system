<!DOCTYPE html>
<html>
<head>
    <title>Payslip for {{ $formattedMonth }}</title>
</head>
<body>
    <h1>Payslip for {{ $formattedMonth }}</h1>
    <p>Dear {{ $employee->name }},</p>
    <p>Please find your payslip for {{ $formattedMonth }} attached.</p>
    <p>Regards,<br>Your HR Team</p>
</body>
</html>
