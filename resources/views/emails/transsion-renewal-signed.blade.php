<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signed Contract Renewal Addendum</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            background-color: white;
            color: #000000;
        }
        .email-wrapper {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .header {
            background-color: #ffffff;
            color: white;
            text-align: center;
            padding: 30px 20px;
        }
        .content {
            padding: 40px 30px;
            color: #000000;
            line-height: 1.7;
        }
        .highlight-id {
            font-family: monospace;
            background-color: #f0f0f0;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
        }
        .info-box {
            background-color: #f8fafc;
            border-left: 4px solid #0066cc;
            padding: 15px 20px;
            margin: 20px 0;
            border-radius: 0 6px 6px 0;
        }
        .footer {
            background-color: #f9f9f9;
            padding: 25px 30px;
            text-align: center;
            font-size: 13px;
            color: #666666;
            border-top: 1px solid #eeeeee;
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="header">
             <img src="https://sbpafrica.group/wp-content/uploads/2023/12/sbpafricalogo.jpg" style="width: 200px; height:70px;" alt="SBP Africa Logo">
        </div>

        <div class="content">
            <p>Dear <strong>HR / Contract Administrator</strong>,</p>

            <p>This is to confirm that the employee contract renewal addendum has been electronically <strong>signed and activated</strong>.</p>

            <div class="info-box">
                <p style="margin: 4px 0;"><strong>Employee Name:</strong> {{ $data['employeename'] }}</p>
                <p style="margin: 4px 0;"><strong>Staff ID:</strong> <span class="highlight-id">{{ $data['staffid'] }}</span></p>
                <p style="margin: 4px 0;"><strong>Position:</strong> {{ $data['position'] ?? 'N/A' }}</p>
                <p style="margin: 4px 0;"><strong>Department:</strong> {{ $data['department'] ?? 'N/A' }}</p>
                <p style="margin: 4px 0;"><strong>Contract Period:</strong> {{ $data['startdate'] }} to {{ $data['enddate'] }}</p>
                <p style="margin: 4px 0;"><strong>Status:</strong> <span style="color: #16a34a; font-weight: bold;">Signed & Activated</span></p>
            </div>

            <p>A copy of the signed PDF document is attached to this email for your records.</p>

            <p style="margin-top: 30px;">Best regards,<br>
            <strong>SBP Africa HRMS System</strong><br>
            SBP Africa Limited</p>
        </div>

        <div class="footer">
            <p>© {{ date('Y') }} SBP Africa Limited. All rights reserved.<br>
            North Legon | Tel: 0302 546 664</p>
        </div>
    </div>
</body>
</html>
