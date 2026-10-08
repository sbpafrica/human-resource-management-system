<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Employment Contract - Action Required</title>
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

        .header h2 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            padding: 40px 30px;
            color: #000000;
            line-height: 1.7;
        }
        .btn {
            display: inline-block;
            background-color: #0066cc;
            color: white !important;
            padding: 14px 30px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin: 25px 0;
            text-align: center;
        }
        .highlight-id {
            font-family: monospace;
            background-color: #f0f0f0;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
        }
        .footer {
            background-color: #f9f9f9;
            padding: 25px 30px;
            text-align: center;
            font-size: 13px;
            color: #666666;
            border-top: 1px solid #eeeeee;
        }
        a {
            color: #0066cc;
            text-decoration: none;
        }
        ul {
            padding-left: 20px;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <!-- Header with Logo -->
        <div class="header">
             <img src="https://sbpafrica.group/wp-content/uploads/2023/12/sbpafricalogo.jpg" style="width: 200px; height:70px;">
        </div>

        <!-- Main Content -->
        <div class="content">
            <p>Dear <strong>{{ $data['employeename'] }}</strong>,</p>

            <p>We are pleased to inform you that your employment contract with <strong>SBP Africa Limited</strong> has been successfully prepared and is now ready for your review and electronic signature.</p>

            <p><strong>Staff ID:</strong> <span class="highlight-id">{{ $data['staffid'] }}</span></p>

            <p style="margin: 30px 0 15px;"><strong>Next Steps:</strong></p>

            <p>Please log in to the SBP Africa Employee Portal within the next <strong>three (3) working days</strong> to:</p>

            <ul>
                <li>Review the full contract details</li>
                <li>Electronically sign the document</li>
                <li>Submit the signed copy to HR</li>
            </ul>

            <p style="text-align: center;">
                <a href="https://employeetrans.sbpafrica.group/login?staffid={{ urlencode($data['staffid']) }}" class="btn">
                    Review & Sign Contract Now
                </a>
            </p>

            <p>If you encounter any issues or have questions, our HR team is ready to assist you:</p>
            <p style="margin: 8px 0;">
                Email: <a href="mailto:hr@sbpafrica.group">hr@sbpafrica.group</a><br>
                Phone: +233 544 128 456
            </p>

            <p>We look forward to welcoming you to the team!</p>

            <p style="margin-top: 30px;">Best regards,<br>
            <strong>HR Department</strong><br>
            SBP Africa Limited</p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>© {{ date('Y') }} SBP Africa Limited. All rights reserved.<br>
            North Legon | Tel: 0302 546 664</p>
        </div>
    </div>
</body>
</html>
