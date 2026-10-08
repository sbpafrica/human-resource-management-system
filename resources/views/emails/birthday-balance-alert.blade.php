<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
        .container { max-width: 600px; margin: 0 auto; padding: 24px; }
        .alert-box { background: #fff3cd; border: 1px solid #ffc107; border-radius: 8px; padding: 16px; margin: 16px 0; }
        .red { color: #dc3545; font-weight: bold; }
        .footer { margin-top: 32px; font-size: 12px; color: #888; }
    </style>
</head>
<body>
<div class="container">
    <h2>⚠️ Birthday SMS — Insufficient Balance Alert</h2>

    <div class="alert-box">
        <p>On <strong>{{ $date }}</strong>, the automated birthday SMS system attempted to send messages but
        <span class="red">{{ $failedCount }} SMS(s) failed</span> due to insufficient balance in the mNotify account.</p>
    </div>

    <p><strong>What this means:</strong></p>
    <ul>
        <li>The affected employees did NOT receive their birthday messages.</li>
        <li>These failed messages have been logged in the system with status <strong>"failed"</strong>.</li>
        <li>They are marked for <strong>automatic retry</strong> once the balance is recharged.</li>
    </ul>

    <p><strong>Action required:</strong></p>
    <ol>
        <li>Log in to your mNotify account and recharge the SMS balance.</li>
        <li>Once recharged, run the retry command:<br>
            <code>php artisan birthday:retry-failed</code><br>
            or it will run automatically on the next scheduled check.
        </li>
    </ol>

    <p>You can view the full report in the <strong>HR System → Birthday Reports</strong> section.</p>

    <div class="footer">
        This is an automated alert from the SBP Africa HR System.<br>
        Do not reply to this email.
    </div>
</div>
</body>
</html>
