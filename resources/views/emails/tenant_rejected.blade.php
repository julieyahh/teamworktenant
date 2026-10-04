<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f5f7fa;
            color: #333;
        }
        .container {
            max-width: 600px;
            margin: 30px auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 30px;
        }
        h2 {
            color: #dc3545;
        }
        .footer {
            margin-top: 25px;
            font-size: 13px;
            color: #888;
            text-align: center;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Tenant Application Rejected</h2>
    <p>Dear {{ $tenantName }},</p>
    <p>We regret to inform you that your tenant application has been <strong>rejected</strong>.</p>
    <p>Reason: <strong>{{ $reason }}</strong></p>
    <p>If you believe this is an error, please contact our management office.</p>
    <p>— Property Management</p>
    <div class="footer">
        <p>This is an automated message. Please do not reply.</p>
    </div>
</div>
</body>
</html>
