<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $report['title'] }}</title>
    <style>
        @page {
            margin: 36px 40px 56px 40px;
        }

        body {
            font-family: Helvetica, Arial, sans-serif;
            font-size: 9pt;
            color: #1f2937;
            margin: 0;
        }

        .header {
            width: 100%;
            border-bottom: 2px solid #4f46e5;
            padding-bottom: 8px;
            margin-bottom: 14px;
        }

        .header td {
            vertical-align: bottom;
        }

        .brand {
            font-size: 15pt;
            font-weight: bold;
            color: #4f46e5;
        }

        .brand-sub {
            font-size: 8pt;
            color: #6b7280;
        }

        .report-title {
            font-size: 13pt;
            font-weight: bold;
            color: #111827;
            text-align: right;
        }

        .report-period {
            font-size: 8.5pt;
            color: #374151;
            text-align: right;
        }

        .summary {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px;
            margin: 0 -6px 12px -6px;
        }

        .summary td {
            width: 25%;
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            padding: 7px 9px;
        }

        .summary-label {
            font-size: 7pt;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #6b7280;
        }

        .summary-value {
            font-size: 13pt;
            font-weight: bold;
            color: #111827;
        }

        .note {
            font-size: 8pt;
            color: #4b5563;
            margin: 0 0 12px 0;
        }

        h2 {
            font-size: 10.5pt;
            color: #111827;
            margin: 14px 0 6px 0;
            padding-bottom: 3px;
            border-bottom: 1px solid #d1d5db;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
        }

        table.data thead th {
            background: #eef2ff;
            color: #312e81;
            font-size: 8pt;
            text-align: left;
            padding: 5px 6px;
            border-bottom: 1px solid #c7d2fe;
        }

        table.data tbody td {
            font-size: 8.5pt;
            padding: 4px 6px;
            border-bottom: 1px solid #e5e7eb;
        }

        table.data tbody tr:nth-child(even) td {
            background: #f9fafb;
        }

        .right {
            text-align: right;
        }

        .left {
            text-align: left;
        }

        .empty {
            font-size: 8.5pt;
            color: #6b7280;
            padding: 10px 2px;
        }

        tr {
            page-break-inside: avoid;
        }

        thead {
            display: table-header-group;
        }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td width="50%">
                <div class="brand">ImportaChina</div>
                <div class="brand-sub">Gestión de importaciones</div>
            </td>
            <td width="50%">
                <div class="report-title">{{ $report['title'] }}</div>
                <div class="report-period">{{ $report['period'] }}</div>
            </td>
        </tr>
    </table>

    <table class="summary">
        <tr>
            @foreach($report['summary'] as $item)
                <td>
                    <div class="summary-label">{{ $item['label'] }}</div>
                    <div class="summary-value">{{ $item['value'] }}</div>
                </td>
            @endforeach
        </tr>
    </table>

    @if(! empty($report['note']))
        <p class="note">{{ $report['note'] }}</p>
    @endif

    @foreach($report['sections'] as $section)
        <h2>{{ $section['title'] }}</h2>

        @if(empty($section['rows']))
            <p class="empty">{{ $section['empty'] ?? 'Sin datos.' }}</p>
        @elseif($section['type'] === 'bars')
            <table class="data">
                <thead>
                    <tr>
                        <th class="left">Día</th>
                        <th class="right">Pedidos</th>
                        <th class="right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($section['rows'] as $row)
                        <tr>
                            <td class="left">{{ $row['label'] }}</td>
                            <td class="right">{{ $row['orders'] }}</td>
                            <td class="right">{{ $row['value'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <table class="data">
                <thead>
                    <tr>
                        @foreach($section['columns'] as $column)
                            <th class="{{ $column['align'] === 'right' ? 'right' : 'left' }}">{{ $column['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($section['rows'] as $row)
                        <tr>
                            @foreach($row as $index => $cell)
                                <td class="{{ ($section['columns'][$index]['align'] ?? 'left') === 'right' ? 'right' : 'left' }}">
                                    {{ $cell }}
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endforeach
</body>
</html>
