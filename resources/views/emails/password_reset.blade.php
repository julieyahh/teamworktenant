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
            color: #b8793e;
            margin-top: 0;
        }
        p {
            line-height: 1.6;
        }
        .btn {
            display: inline-block;
            background-color: #b8793e;
            color: #fff !important;
            text-decoration: none;
            padding: 10px 18px;
            border-radius: 8px;
            margin-top: 15px;
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
    <h2>Password Reset Request</h2>

    <p>Hi {{ $userName }},</p>

    <p>We received a request to reset your password for your Tenant Management account. Click the button below to set a new password:</p>

    <a href="{{ $resetUrl }}" class="btn">Reset Password</a>

    <p>If you didn’t request this change, please ignore this email. This link will expire in 60 minutes for security reasons.</p>

    <p>— Tenant Management Team</p>

    <div class="footer">
        <p>This is an automated message. Please do not reply.</p>
    </div>
</div>
</body>
</html>
