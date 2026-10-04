<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f5f7fa;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 30px auto;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 30px;
        }
        h2 {
            color: #dc3545;
            margin-top: 0;
        }
        p {
            line-height: 1.6;
        }
        ul {
            padding-left: 20px;
            margin-top: 10px;
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
        .highlight {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 10px 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .btn {
            display: inline-block;
            background-color: #dc3545;
            color: #fff !important;
            text-decoration: none;
            padding: 10px 18px;
            border-radius: 8px;
            margin-top: 15px;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Monthly Payment Reminder</h2>
    <p>Dear {{ $tenant->name }},</p>

    <div class="highlight">
        <p><strong>Just a friendly reminder:</strong> Your monthly rent payment is due soon. Please make sure your payment is settled on or before the due date to avoid any penalties.</p>
    </div>

    <ul>
        <li><strong>Rent Balance:</strong> ₱{{ number_format($tenant->rent_balance, 2) }}</li>
        <li><strong>Utility Balance:</strong> ₱{{ number_format($tenant->utility_balance, 2) }}</li>
        <li><strong>Current Status:</strong> {{ ucfirst($tenant->status) }}</li>
    </ul>

    <p>If you have already made your payment, please disregard this message.</p>
    <p>Thank you for your prompt attention and continued tenancy!</p>
    <p>— Property Management</p>

    <a href="#" class="btn">View Your Account</a>

    <div class="footer">
        <p>This is an automated message. Please do not reply.</p>
    </div>
</div>
</body>
</html>
