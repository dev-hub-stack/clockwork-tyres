<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New Wholesale Inquiry</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #333; background: #f4f4f4; margin: 0; padding: 0; }
        .wrapper { max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .header { background-color: #1a1a2e; padding: 24px 32px; }
        .header h1 { color: #ffffff; font-size: 20px; margin: 0; }
        .body { padding: 28px 32px; }
        .label { font-weight: bold; color: #555; font-size: 12px; text-transform: uppercase; margin-top: 16px; }
        .value { font-size: 15px; color: #111; margin-top: 4px; }
        .divider { border: none; border-top: 1px solid #eee; margin: 20px 0; }
        .footer { background: #f9f9f9; padding: 16px 32px; font-size: 12px; color: #999; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="header">
        <h1>New Wholesale Account Inquiry</h1>
    </div>
    <div class="body">
        <p>A new business has submitted a wholesale account inquiry via the wholesale portal. Please review and follow up with the contact below.</p>
        <hr class="divider">

        <div class="label">Business Name</div>
        <div class="value">{{ $inquiryData['business_name'] ?? '—' }}</div>

        <div class="label">Email</div>
        <div class="value">{{ $inquiryData['email'] ?? '—' }}</div>

        @if (!empty($inquiryData['phone']))
        <div class="label">Phone</div>
        <div class="value">{{ $inquiryData['phone'] }}</div>
        @endif

        @if (!empty($inquiryData['country']))
        <div class="label">Country</div>
        <div class="value">{{ $inquiryData['country'] }}</div>
        @endif

        @if (!empty($inquiryData['trade_license_path']))
        <div class="label">Trade License</div>
        <div class="value">Uploaded — check the S3 bucket (<code>dealers/documents/</code>)</div>
        @endif

        <hr class="divider">
        <p>To register this customer, log into the <strong>Reporting CRM</strong>, create a Customer record, then click <strong>Send Wholesale Invite</strong> from the customer detail page.</p>
    </div>
    <div class="footer">TunerStop LLC &mdash; Reporting CRM System</div>
</div>
</body>
</html>
