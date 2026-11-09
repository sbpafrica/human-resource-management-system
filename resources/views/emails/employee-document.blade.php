<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $document->document_title }}</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f3f4f6;
            color: #1f2937;
        }
        .email-wrapper {
            max-width: 600px;
            margin: 30px auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: 1px solid #e5e7eb;
        }
        .header {
            background: linear-gradient(135deg, #1e40af 0%, #2563eb 100%);
            color: #ffffff;
            text-align: center;
            padding: 32px 24px;
        }
        .header h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 700;
            letter-spacing: -0.02em;
        }
        .header p {
            margin: 6px 0 0 0;
            font-size: 14px;
            color: #bfdbfe;
        }
        .content {
            padding: 32px 28px;
            line-height: 1.6;
        }
        .greeting {
            font-size: 17px;
            font-weight: 600;
            color: #111827;
            margin-bottom: 12px;
        }
        .intro-text {
            color: #4b5563;
            font-size: 15px;
            margin-bottom: 24px;
        }
        .card-details {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .detail-row {
            display: table;
            width: 100%;
            padding: 7px 0;
            border-bottom: 1px dashed #e2e8f0;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            display: table-cell;
            width: 35%;
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .detail-value {
            display: table-cell;
            width: 65%;
            font-size: 14px;
            font-weight: 600;
            color: #0f172a;
        }
        .attachment-box {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            border-radius: 0 8px 8px 0;
            padding: 14px 18px;
            margin-bottom: 24px;
        }
        .attachment-box strong {
            color: #1d4ed8;
            font-size: 14px;
            display: block;
            margin-bottom: 4px;
        }
        .attachment-box p {
            margin: 0;
            font-size: 13px;
            color: #1e3a8a;
        }
        .notes-box {
            background: #fffbeb;
            border-left: 4px solid #f59e0b;
            border-radius: 0 8px 8px 0;
            padding: 14px 18px;
            margin-bottom: 24px;
        }
        .notes-box strong {
            color: #b45309;
            font-size: 14px;
            display: block;
            margin-bottom: 4px;
        }
        .notes-box p {
            margin: 0;
            font-size: 13px;
            color: #78350f;
            white-space: pre-line;
        }
        .footer {
            background-color: #f9fafb;
            padding: 24px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
            font-size: 12px;
            color: #6b7280;
        }
        .footer p {
            margin: 4px 0;
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="header">
            <h1>SBP AFRICA</h1>
            <p>Human Resource Management System &bull; Official Notice</p>
        </div>

        <div class="content">
            <div class="greeting">
                Dear {{ $document->employee_name ?: 'Employee' }},
            </div>

            <p class="intro-text">
                Your HR department has issued an official document for you. Please review the details below. The document file has been attached directly to this email for your safe keeping and records.
            </p>

            <div class="card-details">
                <div class="detail-row">
                    <span class="detail-label">Document Title</span>
                    <span class="detail-value">{{ $document->document_title }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Document Type</span>
                    <span class="detail-value">{{ $document->document_type }}</span>
                </div>
                @if($document->staff_id)
                <div class="detail-row">
                    <span class="detail-label">Staff ID</span>
                    <span class="detail-value">{{ $document->staff_id }}</span>
                </div>
                @endif
                @if($document->client_name)
                <div class="detail-row">
                    <span class="detail-label">Client / Company</span>
                    <span class="detail-value">{{ $document->client_name }}</span>
                </div>
                @endif
                <div class="detail-row">
                    <span class="detail-label">Issue Date</span>
                    <span class="detail-value">{{ now()->format('d M, Y') }}</span>
                </div>
                @if($document->file_name)
                <div class="detail-row">
                    <span class="detail-label">Attached File</span>
                    <span class="detail-value">{{ $document->file_name }}</span>
                </div>
                @endif
            </div>

            @if(!empty($document->notes))
            <div class="notes-box">
                <strong>Message from HR Management:</strong>
                <p>{{ $document->notes }}</p>
            </div>
            @endif

            <div class="attachment-box">
                <strong>📎 File Attachment Notice:</strong>
                <p>The document (<strong>{{ $document->file_name }}</strong>) is attached to this email message. You can download, preview, or print it directly from your email client.</p>
            </div>

            <p style="font-size: 13px; color: #6b7280; margin-top: 24px;">
                If you have any questions or concerns regarding this document, please contact your Human Resources team or reply directly to this email.
            </p>
        </div>

        <div class="footer">
            <p><strong>SBP Africa Human Resource Management</strong></p>
            <p>This is an automated communication generated by SBP Africa HRMS.</p>
            <p>&copy; {{ date('Y') }} SBP Africa. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
