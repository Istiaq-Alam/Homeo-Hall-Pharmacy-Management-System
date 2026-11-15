<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $chamberName }} financial report</title>
<style>
    @page { margin: 30px 34px 54px; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: "DejaVu Sans", sans-serif; color: #1d1d1f; font-size: 10.5px; line-height: 1.45; }
    table { border-collapse: collapse; }
    .header { width: 100%; margin-bottom: 15px; }
    .header td { vertical-align: middle; }
    .header-left { width: 62%; }
    .header-right { width: 38%; text-align: right; }
    .identity { width: 100%; }
    .identity td { vertical-align: middle; }
    .logo-cell { width: 56px; padding-right: 12px; }
    .logo-mark { width: 44px; height: 44px; line-height: 44px; text-align: center; border-radius: 11px; background: #0066cc; color: #fff; font-size: 21px; font-weight: bold; }
    .logo-image { width: 44px; height: 44px; padding: 5px; border: 1px solid #e0e0e0; border-radius: 11px; background: #fff; }
    h1 { margin: 0; font-size: 23px; line-height: 1.15; font-weight: bold; letter-spacing: -0.3px; }
    .administrator { margin: 5px 0 0; color: #555; font-size: 10.5px; }
    .eyebrow { color: #0066cc; font-size: 8px; font-weight: bold; letter-spacing: 1.4px; text-transform: uppercase; }
    .report-title { margin: 4px 0 2px; font-size: 15px; font-weight: bold; }
    .period { color: #333; font-size: 10.5px; font-weight: bold; }
    .generated { margin-top: 3px; color: #8a8a8a; font-size: 8px; }
    .rule { height: 2px; margin: 0 0 16px; background: #1d1d1f; }
    .hero { width: 100%; margin-bottom: 16px; border-radius: 12px; background: #272729; color: #fff; }
    .hero td { padding: 17px 20px; vertical-align: middle; }
    .hero-main { width: 66%; }
    .hero-side { width: 34%; text-align: right; }
    .hero-label { margin-bottom: 4px; color: #2997ff; font-size: 8px; font-weight: bold; letter-spacing: 1.3px; text-transform: uppercase; }
    .hero-value { color: #fff; font-size: 31px; font-weight: bold; line-height: 1.1; }
    .hero-note { margin-top: 5px; color: #c7c7cc; font-size: 8.5px; }
    .hero-period { color: #fff; font-size: 11px; font-weight: bold; line-height: 1.45; }
    .hero-range { margin-top: 4px; color: #c7c7cc; font-size: 8.5px; }
    .columns { width: 100%; margin-bottom: 14px; }
    .columns > tbody > tr > td { vertical-align: top; }
    .col-left { width: 60%; padding-right: 7px; }
    .col-right { width: 40%; padding-left: 7px; }
    .panel { padding: 13px 15px; border: 1px solid #e0e0e0; border-radius: 10px; background: #fff; }
    h2 { margin: 0 0 8px; font-size: 12px; font-weight: bold; letter-spacing: -0.1px; }
    .statement, .activity { width: 100%; }
    .statement td, .activity td { padding: 6px 0; border-bottom: 1px solid #f0f0f0; }
    .statement tr:last-child td, .activity tr:last-child td { border-bottom: 0; }
    .amount, .activity-value { text-align: right; white-space: nowrap; }
    .statement .label { color: #444; }
    .statement .subtotal td { padding-top: 8px; border-top: 1px solid #d2d2d7; border-bottom: 0; font-weight: bold; }
    .statement .total td { padding-top: 9px; border-top: 2px solid #1d1d1f; border-bottom: 0; font-size: 11px; font-weight: bold; }
    .activity .label { color: #555; }
    .activity .activity-value { color: #1d1d1f; font-weight: bold; }
    .activity .activity-group td { padding-top: 10px; color: #0066cc; font-size: 8px; font-weight: bold; text-transform: uppercase; }
    .note { margin-top: 14px; padding: 9px 12px; border-left: 3px solid #0066cc; background: #f5f5f7; color: #555; font-size: 8.5px; line-height: 1.55; }
    .signature { width: 230px; margin-top: 28px; }
    .signature-line { margin-top: 32px; padding-top: 5px; border-top: 1px solid #b0b0b0; }
    .signature-name { font-size: 10px; font-weight: bold; }
    .signature-role { color: #7a7a7a; font-size: 8.5px; }
    .footer { position: fixed; left: 0; right: 0; bottom: -37px; padding-top: 7px; border-top: 1px solid #e0e0e0; color: #8a8a8a; font-size: 8px; }
    .footer table { width: 100%; }
    .footer .footer-right { text-align: right; }
</style>
</head>
<body>
    <table class="header">
        <tr>
            <td class="header-left">
                <table class="identity">
                    <tr>
                        <td class="logo-cell">
                            @if($logoData)
                                <img class="logo-image" src="{{ $logoData }}" alt="{{ $chamberName }} logo">
                            @else
                                <div class="logo-mark">{{ mb_strtoupper(mb_substr($chamberName, 0, 1)) }}</div>
                            @endif
                        </td>
                        <td>
                            <h1>{{ $chamberName }}</h1>
                            <p class="administrator">Administrator: {{ $administratorName }}</p>
                        </td>
                    </tr>
                </table>
            </td>
            <td class="header-right">
                <div class="eyebrow">Chamber finance</div>
                <div class="report-title">Financial report</div>
                <div class="period">{{ \Illuminate\Support\Carbon::parse($range['from'])->format('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($range['to'])->format('d M Y') }}</div>
                <div class="generated">Generated {{ $generatedAt->format('d M Y, h:i A') }}</div>
            </td>
        </tr>
    </table>
    <div class="rule"></div>

    <table class="hero">
        <tr>
            <td class="hero-main">
                <div class="hero-label">Net operating profit</div>
                <div class="hero-value">BDT {{ number_format($summary['net_profit'], 2) }}</div>
                <div class="hero-note">Gross profit minus other chamber operating expenses</div>
            </td>
            <td class="hero-side">
                <div class="hero-label">Reporting period</div>
                <div class="hero-period">{{ \Illuminate\Support\Carbon::parse($range['from'])->format('d M') }} – {{ \Illuminate\Support\Carbon::parse($range['to'])->format('d M Y') }}</div>
                <div class="hero-range">{{ $summary['sales_count'] }} sale(s) · {{ $summary['expenses_count'] }} expense(s)</div>
            </td>
        </tr>
    </table>

    <table class="columns">
        <tr>
            <td class="col-left">
                <div class="panel">
                    <h2>Trading summary</h2>
                    <table class="statement">
                        <tr><td class="label">Sales revenue</td><td class="amount">BDT {{ number_format($summary['sales'], 2) }}</td></tr>
                        <tr><td class="label">Cost of goods sold</td><td class="amount">BDT {{ number_format($summary['cogs'], 2) }}</td></tr>
                        <tr class="subtotal"><td>Gross profit</td><td class="amount">BDT {{ number_format($summary['gross_profit'], 2) }}</td></tr>
                        <tr><td class="label">Other operating expenses</td><td class="amount">BDT {{ number_format($summary['expenses'], 2) }}</td></tr>
                        <tr class="total"><td>Net operating profit</td><td class="amount">BDT {{ number_format($summary['net_profit'], 2) }}</td></tr>
                    </table>
                </div>
            </td>
            <td class="col-right">
                <div class="panel">
                    <h2>Chamber activity</h2>
                    <table class="activity">
                        <tr><td class="label">Sales records</td><td class="activity-value">{{ number_format($summary['sales_count']) }}</td></tr>
                        <tr><td class="label">Units sold</td><td class="activity-value">{{ number_format($summary['units_sold']) }}</td></tr>
                        <tr><td class="label">Purchase records</td><td class="activity-value">{{ number_format($summary['purchases_count']) }}</td></tr>
                        <tr><td class="label">Units purchased</td><td class="activity-value">{{ number_format($summary['units_purchased']) }}</td></tr>
                        <tr class="activity-group"><td colspan="2">Inventory</td></tr>
                        <tr><td class="label">Closing inventory value</td><td class="activity-value">BDT {{ number_format($summary['inventory_value'], 2) }}</td></tr>
                        <tr><td class="label">Stock purchases</td><td class="activity-value">BDT {{ number_format($summary['purchases'], 2) }}</td></tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <div class="note">
        Purchases are inventory acquisitions and are not treated as operating expenses. Gross profit = sales revenue − historical cost of goods sold.
        Net operating profit = gross profit − other operating expenses. Closing inventory is valued at weighted-average cost.
    </div>

    <div class="signature">
        <div class="signature-line">
            <div class="signature-name">{{ $administratorName }}</div>
            <div class="signature-role">Chamber administrator · {{ $chamberName }}</div>
        </div>
    </div>

    <div class="footer">
        <table>
            <tr>
                <td>Homeo-Hall · Private chamber financial report</td>
                <td class="footer-right">© {{ $generatedAt->format('Y') }} Homeo-Hall. All rights reserved.</td>
            </tr>
        </table>
    </div>
</body>
</html>
