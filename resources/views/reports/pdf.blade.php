<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11px;
            color: #1f2937;
            margin: 18px;
        }
        .header {
            border-bottom: 2px solid #10b981;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
            color: #065f46;
        }
        .header .meta {
            margin-top: 4px;
            font-size: 10px;
            color: #6b7280;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        .summary-table td {
            border: 1px solid #d1d5db;
            background: #f0fdf4;
            padding: 8px 10px;
            text-align: center;
            width: 25%;
        }
        .summary-label {
            font-size: 9px;
            text-transform: uppercase;
            color: #4b5563;
            display: block;
            margin-bottom: 2px;
        }
        .summary-val {
            font-size: 13px;
            font-weight: bold;
            color: #065f46;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
        }
        table.data-table th {
            background: #065f46;
            color: #ffffff;
            text-align: left;
            padding: 6px 7px;
            font-size: 10px;
            border: 1px solid #065f46;
        }
        table.data-table td {
            padding: 5px 7px;
            border: 1px solid #e5e7eb;
            font-size: 10px;
        }
        table.data-table tr:nth-child(even) td {
            background: #f9fafb;
        }
        .footer {
            margin-top: 14px;
            font-size: 9px;
            color: #6b7280;
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Fiona's Mushroom Farm — {{ $title }}</h1>
        <div class="meta">
            Generated: {{ $generatedAt }} &bull; Filter: {{ $filterLabel }}
        </div>
    </div>

    <table class="summary-table">
        <tr>
            @foreach ($summaryCards as $card)
                <td>
                    <span class="summary-label">{{ $card['label'] }}</span>
                    <span class="summary-val">{{ $card['value'] }}</span>
                </td>
            @endforeach
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                @foreach ($columns as $col)
                    <th>{{ $col }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($columns) }}" style="text-align:center; padding: 14px;">No records found for the selected filter.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        KabuTech IoT Mushroom Monitoring System &bull; Showing {{ count($rows) }} record(s)
    </div>
</body>
</html>
