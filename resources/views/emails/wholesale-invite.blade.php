<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Set Your Wholesale Password</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #333; background: #f4f4f4; margin: 0; padding: 0; }
        .wrapper { max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .header { background-color: #1a1a2e; padding: 28px 32px; }
        .header h1 { color: #ffffff; font-size: 22px; margin: 0; }
        .header p { color: #a0aec0; margin: 6px 0 0; font-size: 13px; }
        .body { padding: 32px; }
        .body p { line-height: 1.7; margin-bottom: 16px; }
        .btn { display: inline-block; background-color: #e53e3e; color: #ffffff !important; text-decoration: none; padding: 14px 32px; border-radius: 6px; font-size: 15px; font-weight: bold; margin: 8px 0 20px; }
        .note { font-size: 12px; color: #888; margin-top: 20px; word-break: break-all; }
        .footer { background: #f9f9f9; padding: 16px 32px; font-size: 12px; color: #999; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>Welcome to TunerStop Wholesale</h1>
        <p>Your account is ready</p>
    </div>
    <div class="body">
        <p>Hi <strong>{{ $customer->business_name ?? $customer->first_name }}</strong>,</p>

        <p>Your wholesale account has been set up. Click the button below to set your password and gain access to the wholesale portal.</p>

        <a href="{{ $setPasswordUrl }}" class="btn">Set My Password</a>

        <p>This link will expire in <strong>48 hours</strong>. If you didn't request this, please ignore this email.</p>

        <p class="note">If the button doesn't work, copy and paste this URL into your browser:<br>{{ $setPasswordUrl }}</p>
    </div>
    <div class="footer">TunerStop LLC &mdash; Wholesale Portal</div>
</div>
</body>
</html>
