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
        ul {
            padding-left: 20px;
        }
        li {
            margin-bottom: 5px;
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
    <h2>Account Voided</h2>
    <p>Dear {{ $tenant->name }},</p>
    <p>Your account has been <strong>voided</strong> because your deposit payment was not completed within 7 days of registration.</p>

    <ul>
        <li>Rent Balance: ₱{{ number_format($tenant->rent_balance, 2) }}</li>
        <li>Utility Balance: ₱{{ number_format($tenant->utility_balance, 2) }}</li>
        <li>Current Status: {{ ucfirst($tenant->status) }}</li>
    </ul>

    <p>If you believe this is an error, please contact our management office immediately.</p>
    <p>Thank you for your understanding.</p>
    <p>— Property Management</p>

    <div class="footer">
        <p>This is an automated message. Please do not reply.</p>
    </div>
</div>
</body>
</html>
