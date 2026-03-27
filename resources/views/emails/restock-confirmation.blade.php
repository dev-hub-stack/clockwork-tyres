<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restock Alert Requested</title>
</head>
<body style="margin:0; padding:0; background:#ece7de; font-family:Arial, sans-serif; color:#1f2933;">
    <table role="presentation" style="width:100%; border-collapse:collapse; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" style="width:100%; max-width:640px; border-collapse:collapse; background:#fffdf9; border:1px solid #d9d2c6;">
                    <tr>
                        <td style="padding:28px 32px 12px; text-align:center; background:#162029;">
                            @if (!empty($emailLogoUrl))
                                <img src="{{ $emailLogoUrl }}" alt="TunerStop" style="max-width:180px; height:auto;">
                            @else
                                <div style="font-size:24px; font-weight:700; letter-spacing:0.08em; color:#f8f4ec;">TUNERSTOP</div>
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <div style="font-size:12px; font-weight:700; letter-spacing:0.16em; color:#8a6d46; text-transform:uppercase;">Restock Alert</div>
                            <h1 style="margin:12px 0 10px; font-size:28px; line-height:1.2; color:#162029;">We'll notify you when this {{ strtolower($item['type_label'] ?? 'item') }} is available.</h1>
                            <p style="margin:0 0 24px; font-size:15px; line-height:1.6; color:#52606d;">Your request has been saved. As soon as inventory or ETA becomes available for this item, we'll send another email.</p>
                            <table role="presentation" style="width:100%; border-collapse:collapse; background:#f7f3ec; border:1px solid #e1d8ca;">
                                <tr>
                                    @if (!empty($item['image_url']))
                                        <td style="width:180px; padding:20px; vertical-align:top;">
                                            <img src="{{ $item['image_url'] }}" alt="{{ $item['name'] }}" style="display:block; width:140px; max-width:100%; height:auto; border:0;">
                                        </td>
                                    @endif
                                    <td style="padding:20px; vertical-align:top;">
                                        <div style="font-size:12px; font-weight:700; letter-spacing:0.16em; color:#9b7b4b; text-transform:uppercase;">{{ $item['type_label'] ?? 'Item' }}</div>
                                        <div style="margin-top:8px; font-size:24px; line-height:1.25; font-weight:700; color:#162029;">{{ $item['name'] }}</div>
                                        @if (!empty($item['sku']))
                                            <div style="margin-top:10px; font-size:14px; color:#52606d;"><strong>SKU:</strong> {{ $item['sku'] }}</div>
                                        @endif
                                        @foreach (($item['detail_lines'] ?? []) as $line)
                                            <div style="margin-top:6px; font-size:14px; color:#52606d;">{{ $line }}</div>
                                        @endforeach
                                    </td>
                                </tr>
                            </table>
                            @if (!empty($item['product_url']))
                                <table role="presentation" style="margin-top:24px; border-collapse:collapse;">
                                    <tr>
                                        <td>
                                            <a href="{{ $item['product_url'] }}" style="display:inline-block; padding:14px 22px; background:#a33a2b; color:#fffdf9; text-decoration:none; font-size:14px; font-weight:700; letter-spacing:0.08em; text-transform:uppercase;">View Item</a>
                                        </td>
                                    </tr>
                                </table>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>