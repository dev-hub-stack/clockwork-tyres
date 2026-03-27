<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html charset=UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $isEta ? 'ETA Update' : 'Back In Stock' }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        body{
            background-color: #dbdbdb;
            color:#000;
            font-family: sans-serif;
            padding: 0;
            margin: 0;
        }

        .email-shell {
            width:100%;
            margin:0 auto;
            padding:30px;
            border-collapse:collapse;
        }

        .content-card {
            background-color:#fff;
            width:80%;
            margin:0 auto;
            padding:30px;
            border-collapse:collapse;
        }

        .cta-shell {
            width:80%;
            text-align:center;
            border:none;
            margin:40px auto 0;
            border-collapse:collapse;
        }

        .footer-shell {
            width:88%;
            margin:30px auto 0;
            border-collapse:collapse;
        }

        .hero-title {
            text-align:center;
            font-size:48px;
            line-height:1.05;
            margin:0;
        }

        .mobile-stack {
            width:75%;
            vertical-align:top;
        }

        .mobile-image {
            width:25%;
            vertical-align:bottom;
        }

        .spec-card {
            width:100%;
            border:2px solid #dbdbdb;
            border-spacing:initial;
            border-collapse:separate;
        }

        .spec-label-cell {
            width:20%;
            background-color:#dbdbdb;
            font-weight:800;
            text-align:center;
        }

        .cta-link {
            color:#fff;
            background-color:#595fdd;
            padding:10px;
            margin:0;
            border-radius:10px;
            font-size:25px;
            font-weight:lighter;
            display:block;
            text-decoration:none;
            text-align:center;
        }

        @media only screen and (max-width: 640px) {
            .email-shell {
                padding:18px 10px !important;
            }

            .content-card,
            .cta-shell,
            .footer-shell {
                width:100% !important;
            }

            .content-card {
                padding:18px 14px !important;
            }

            .hero-title {
                font-size:34px !important;
            }

            .mobile-stack,
            .mobile-image,
            .mobile-stack-left,
            .mobile-stack-right,
            .spec-label-cell,
            .spec-value-cell,
            .spec-spacer {
                display:block !important;
                width:100% !important;
                text-align:left !important;
            }

            .mobile-image {
                padding-bottom:16px !important;
            }

            .mobile-image img {
                width:100% !important;
                max-width:220px !important;
                margin:0 auto !important;
                display:block !important;
            }

            .mobile-stack-right {
                padding-top:10px !important;
            }

            .spec-card {
                border-collapse:collapse !important;
            }

            .spec-label-cell {
                padding:12px 10px !important;
                text-align:center !important;
            }

            .spec-value-cell {
                padding:14px 12px !important;
            }

            .spec-spacer,
            .spec-top-gap,
            .spec-bottom-gap {
                display:none !important;
                height:0 !important;
            }

            .spec-row td {
                display:block !important;
                width:100% !important;
                box-sizing:border-box !important;
                padding:2px 0 !important;
                text-align:left !important;
            }

            .cta-link {
                font-size:18px !important;
                padding:14px 12px !important;
            }

            .footer-shell td {
                font-size:14px !important;
            }
        }
    </style>
</head>
<body style="background: #dbdbdb;">
    <table role="presentation" class="email-shell" style="width:100%;margin:0 auto; padding:30px; border-collapse:collapse;">
        <tr>
            <td>
                <table role="presentation" style="width:100%;margin:0 auto; border-collapse:collapse;">
                    <tr>
                        <td style="text-align: center;">
                            @if (!empty($emailLogoUrl))
                                <img style="text-align:center; width:150px;" src="{{ $emailLogoUrl }}" class="mylogo" alt="Clockwork"/>
                            @else
                                <div style="font-size:24px; font-weight:700; letter-spacing:0.08em; color:#162029;">TUNERSTOP</div>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr>
            <td>
                <table role="presentation" class="content-card" style="background-color: #fff; width:80%;margin:0 auto; padding:30px; border-collapse:collapse;">
                    <tr>
                        <td>
                            <table role="presentation" style="background-color: #fff; padding: 15px; border-radius: 10px; width: 100%; border-collapse:collapse;">
                                <tr>
                                    <td>
                                        <table role="presentation" style="width: 100%; border-collapse:collapse;">
                                            <tr>
                                                <td>
                                                    <h1 class="hero-title" style="text-align: center; font-size:48px; line-height:1.05; margin:0;">{{ $isEta ? 'ETA Notification' : 'BACK IN STOCK!' }}</h1>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <table role="presentation" style="width:100%; border-collapse:collapse;">
                                            <tr>
                                                @if (!empty($item['image_url']))
                                                    <td class="mobile-image" style="width: 25%; vertical-align: bottom;">
                                                        <img src="{{ $item['image_url'] }}" style="max-width: 100%;" alt="{{ $item['name'] }}"/>
                                                    </td>
                                                @endif
                                                <td style="width:0%"></td>
                                                <td class="mobile-stack" style="width: 75%;vertical-align: top;">
                                                    <table role="presentation" style="width: 100%; border-collapse:collapse;">
                                                        <tr style="vertical-align: top;">
                                                            <td class="mobile-stack-left" style="text-align: left;">
                                                                <h5 style="margin: 0; color:#000; font-size:13px;">{{ $item['name'] }}</h5>
                                                                @if (!empty($item['finish_name']))
                                                                    <h5 style="margin: 0; color:#000; font-size:13px;">{{ $item['finish_name'] }}</h5>
                                                                @endif
                                                            </td>
                                                            <td class="mobile-stack-right" style="text-align: right;">
                                                                @if (!empty($item['sku']))
                                                                    <h6 style="margin: 0;color:#000; font-size:13px;">Part No. : {{ $item['sku'] }}</h6>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    </table>
                                                    <table role="presentation" style="width: 100%; border-collapse:collapse;">
                                                        <tr style="vertical-align: top;">
                                                            <td style="height: 10px;"></td>
                                                        </tr>
                                                    </table>

                                                    @if (($item['type'] ?? null) === 'wheel')
                                                        <table role="presentation" class="spec-card" style="width: 100%; border:2px solid #dbdbdb; border-spacing: initial; border-collapse:separate;">
                                                            <tr>
                                                                <td class="spec-label-cell" style="width: 20%; background-color: #dbdbdb; font-weight: 800; text-align: center;">
                                                                    WHEEL <br/> INFO
                                                                </td>
                                                                <td class="spec-value-cell" style="width: 80%;">
                                                                    <table role="presentation" style="width: 100%; border-collapse:collapse;">
                                                                        <tr class="spec-top-gap" style="height: 10px;">
                                                                            <td style="text-align: right;"></td>
                                                                            <td></td>
                                                                            <td class="spec-spacer"></td>
                                                                            <td style="text-align: right;"></td>
                                                                            <td></td>
                                                                        </tr>
                                                                        <tr class="spec-row">
                                                                            <td style="text-align: right; font-size: 13px; font-weight: 600;">Size:</td>
                                                                            <td style="text-align: left; font-size: 13px; font-weight: 600;">{{ $item['size'] ?? '-' }}</td>
                                                                            <td class="spec-spacer"></td>
                                                                            <td style="text-align: right; font-size: 13px; font-weight: 600;">Bolt Pattern:</td>
                                                                            <td style="text-align: left; font-size: 13px; font-weight: 600;">{{ $item['bolt_pattern'] ?? '-' }}</td>
                                                                        </tr>
                                                                        <tr class="spec-row">
                                                                            <td style="text-align: right; font-size: 13px; font-weight: 600;">Weight:</td>
                                                                            <td style="text-align: left; font-size: 13px; font-weight: 600;">{{ $item['weight'] ?? '-' }}</td>
                                                                            <td class="spec-spacer"></td>
                                                                            <td style="text-align: right; font-size: 13px; font-weight: 600;">Offset:</td>
                                                                            <td style="text-align: left; font-size: 13px; font-weight: 600;">{{ $item['offset'] ?? '-' }}</td>
                                                                        </tr>
                                                                        <tr class="spec-row">
                                                                            <td style="text-align: right; font-size: 13px; font-weight: 600;">Bore:</td>
                                                                            <td style="text-align: left; font-size: 13px; font-weight: 600;">{{ $item['hub_bore'] ?? '-' }}</td>
                                                                            <td class="spec-spacer"></td>
                                                                            <td style="text-align: right; font-size: 13px; font-weight: 600;">Load limit:</td>
                                                                            <td style="text-align: left; font-size: 13px; font-weight: 600;">{{ $item['max_wheel_load'] ?? '-' }}</td>
                                                                        </tr>
                                                                        <tr class="spec-row">
                                                                            <td style="text-align: right; font-size: 13px; font-weight: 600;">Warranty:</td>
                                                                            <td style="text-align: left; font-size: 13px; font-weight: 600;">{{ $item['backspacing'] ?? '-' }}</td>
                                                                            <td class="spec-spacer"></td>
                                                                        </tr>
                                                                        <tr class="spec-bottom-gap" style="height: 10px;">
                                                                            <td style="text-align: right;"></td>
                                                                            <td></td>
                                                                            <td class="spec-spacer"></td>
                                                                            <td style="text-align: right;"></td>
                                                                            <td></td>
                                                                        </tr>
                                                                    </table>
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    @else
                                                        <table role="presentation" class="spec-card" style="width: 100%; border:2px solid #dbdbdb; border-spacing: initial; border-collapse:separate;">
                                                            <tr>
                                                                <td class="spec-label-cell" style="width: 20%; background-color: #dbdbdb; font-weight: 800; text-align: center;">
                                                                    {{ strtoupper($item['type_label'] ?? 'ITEM') }} <br/> INFO
                                                                </td>
                                                                <td class="spec-value-cell" style="width: 80%; padding:14px;">
                                                                    @foreach (($item['detail_lines'] ?? []) as $line)
                                                                        <div style="font-size: 13px; font-weight: 600; color:#000; margin-bottom:6px;">{{ $line }}</div>
                                                                    @endforeach
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    @endif

                                                    @if($isEta && !empty($item['eta']))
                                                        <table role="presentation" style="width: 100%; border-collapse:collapse;">
                                                            <tr style="vertical-align: top;">
                                                                <td>
                                                                    <h4 style="text-align: left;">Estimated Arrival Date: {{ $item['eta'] }}</h4>
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
                        </td>
                    </tr>
                    <tr>
                        <td style="height:20px"></td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr>
            <td>
                <table role="presentation" class="cta-shell" style="width: 80%; text-align: center; border: none; margin:40px auto 0; border-collapse:collapse;">
                    <tr>
                        <td>
                            <a class="cta-link" href="{{ $item['product_url'] ?? '#' }}" style="color: #fff; background-color: #595fdd; padding: 10px; margin: 0; border-radius: 10px; font-size: 25px; font-weight: lighter; display: block; text-decoration: none; text-align: center;">Order Now</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr>
            <td>
                <table role="presentation" class="footer-shell" style="width:88%;margin:30px auto 0; border-collapse:collapse;">
                    <tr>
                        <td style="text-align:center;">
                            <span style="font-size:16px;font-family:'Nunito', sans-serif;font-weight:700;color:#000;">powered by</span>
                            @if (!empty($emailLogoUrl))
                                <img style="width:120px; text-align:center;margin-bottom:-6px;" src="{{ $emailLogoUrl }}" class="mylogo" alt="Clockwork" />
                            @else
                                <span style="font-size:16px;font-family:'Nunito', sans-serif;font-weight:700;color:#000; margin-left:8px;">TUNERSTOP</span>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>