<!DOCTYPE html>
<html>
<head>
    <title>Quote Received</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <p>Dear {{ $record->customer->business_name ?? $record->customer->name ?? 'Customer' }},</p>

    <p>Please find attached the quote <strong>{{ $record->quote_number }}</strong> for your review.</p>

    <p><strong>Quote Details:</strong></p>
    <ul>
        <li><strong>Date:</strong> {{ $record->issue_date ? $record->issue_date->format('M d, Y') : date('M d, Y') }}</li>
        <li><strong>Valid Until:</strong> {{ $record->valid_until ? $record->valid_until->format('M d, Y') : 'N/A' }}</li>
        <li><strong>Total Amount:</strong> {{ number_format($record->total, 2) }}</li>
    </ul>

    <p>If you have any questions or would like to proceed, please let us know.</p>

    <p>Best regards,<br>
    {{ \App\Modules\Settings\Models\CompanyBranding::getActive()?->company_name ?? 'TunerStop LLC' }}</p>
</body>
</html>
