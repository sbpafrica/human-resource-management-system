<!DOCTYPE html>
<html>
<head>
    <title>Leave Application Form</title>
    <style>
        /* Import Poppins from Google Fonts */
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;700&display=swap');

        body {
            font-family: 'Poppins', Arial, sans-serif;
            margin: 15px;
            color: #333;
            font-size: 14px;
        }
        .logo {
            text-align: center;
            margin-bottom: 15px;
        }
        .logo img {
            max-width: 180px;
            height: auto;
        }
        .container {
            max-width: 750px;
            margin: 0 auto;
            border: 1px solid #ccc;
            padding: 15px;
        }
        .header {
            text-align: center;
            margin-bottom: 15px;
        }
        .header h1 {
            margin: 0;
            font-size: 22px;
            color: #2c3e50;
        }
        .header p {
            margin: 3px 0;
            font-size: 12px;
            color: #7f8c8d;
        }
        .section {
            margin-bottom: 15px;
        }
        .section h2 {
            font-size: 18px;
            color: #2c3e50;
            border-bottom: 1px solid #ccc;
            padding-bottom: 4px;
            margin-bottom: 8px;
        }
        .grid {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 0 -4px;
        }
        .field {
            flex: 0 0 calc(50% - 8px);
            margin-bottom: 8px;
            padding: 0 4px;
            box-sizing: border-box;
        }
        .field label {
            font-weight: bold;
            display: block;
            font-size: 13px;
            margin-bottom: 3px;
        }
        .field span {
            display: block;
            font-size: 12px;
            word-wrap: break-word;
            max-width: 100%;
        }
        .footer {
            text-align: center;
            margin-top: 15px;
            font-size: 12px;
            color: #7f8c8d;
        }
        .status-approved {
            color: #27ae60;
            font-weight: bold;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="logo">
        <img src="{{ public_path('images/sbpafricalogo.png') }}" alt="SBP Africa Ltd. Logo">
    </div>
    <div class="container">
        <div class="header">
            <h1>Leave Application Form</h1>
            <p>SBP Africa Ltd.</p>
            <p>Generated on {{ \Carbon\Carbon::now()->format('jS F, Y') }}</p>
        </div>

        <div class="section">
            <h2>Employee Details</h2>
            <div class="grid">
                <div class="field">
                    <label>Staff ID</label>
                    <span>{{ $leave->ida }}</span>
                </div>
                <div class="field">
                    <label>Employee Name</label>
                    <span>{{ $employee->employeename ?? 'N/A' }}</span>
                </div>
            </div>
        </div>

        <div class="section">
            <h2>Leave Details</h2>
            <div class="grid">
                <div class="field">
                    <label>Leave Type</label>
                    <span>{{ $leave->type ?? 'N/A' }}</span>
                </div>
                <div class="field">
                    <label>Total Leave Days</label>
                    <span>{{ $leaveDays ?? 'N/A' }}</span>
                </div>
                <div class="field">
                    <label>Start Date</label>
                    <span>{{ $formattedStartDate }}</span>
                </div>
                <div class="field">
                    <label>End Date</label>
                    <span>{{ $formattedEndDate }}</span>
                </div>
                <div class="field">
                    <label>Reason</label>
                    <span>{{ $leave->reason ?? 'N/A' }}</span>
                </div>
                <div class="field">
                    <label>Status</label>
                    <span class="status-approved">Approved</span>
                </div>
            </div>
        </div>

        <div class="section">
            <h2>Manager Details</h2>
            <div class="grid">
                <div class="field">
                    <label>Manager Name</label>
                    <span>{{ $leave->managername ?? 'N/A' }}</span>
                </div>
                <div class="field">
                    <label>Phone</label>
                    <span>{{ $leave->phone ?? 'N/A' }}</span>
                </div>
            </div>
        </div>

        <div class="footer">
            <p>This is an automatically generated document from the SBP Africa Ltd. HR System.</p>
            <p>Contact HR for any discrepancies or inquiries.</p>
        </div>
    </div>
</body>
</html>
