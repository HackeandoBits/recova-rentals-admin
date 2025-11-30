<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Reservas - Recova Rentals</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #333;
        }

        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 3px solid #8b5cf6;
        }

        .logo {
            font-size: 28px;
            font-weight: bold;
            color: #8b5cf6;
            margin-bottom: 5px;
        }

        .subtitle {
            font-size: 13px;
            color: #666;
            margin-bottom: 10px;
        }

        .period {
            font-size: 12px;
            font-weight: bold;
            color: #333;
            margin-top: 10px;
        }

        .generated {
            font-size: 9px;
            color: #999;
            margin-top: 5px;
        }

        .stats-grid {
            display: table;
            width: 100%;
            margin: 20px 0;
        }

        .stat-card {
            display: table-cell;
            width: 25%;
            padding: 12px;
            text-align: center;
            border: 1px solid #e5e7eb;
            background: #f9fafb;
        }

        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #8b5cf6;
            margin-bottom: 3px;
        }

        .stat-label {
            font-size: 9px;
            color: #666;
            text-transform: uppercase;
        }

        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #333;
            margin: 20px 0 10px 0;
            padding-bottom: 5px;
            border-bottom: 2px solid #8b5cf6;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }

        thead {
            background: #8b5cf6;
            color: white;
        }

        th,
        td {
            padding: 8px;
            text-align: left;
            border: 1px solid #e5e7eb;
            font-size: 9px;
        }

        tbody tr:nth-child(even) {
            background: #f9fafb;
        }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-confirmed {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-completed {
            background: #dbeafe;
            color: #1e40af;
        }

        .page-break {
            page-break-after: always;
        }

        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8px;
            color: #999;
            padding: 10px 0;
            border-top: 1px solid #e5e7eb;
        }
    </style>
</head>

<body>
    {{-- Header --}}
    <div class="header">
        <div class="logo">RECOVA RENTALS</div>
        <div class="subtitle">Reporte de Reservas y Estadísticas</div>
        <div class="period">Período: {{ $period_from }} - {{ $period_to }}</div>
        <div class="generated">Generado el {{ $generated_at }}</div>
    </div>

    {{-- Statistics Cards --}}
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value">{{ $total_interviews }}</div>
            <div class="stat-label">Total Solicitudes</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ $status_counts['confirmed'] }}</div>
            <div class="stat-label">Confirmadas</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ $status_counts['pending'] }}</div>
            <div class="stat-label">Pendientes</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ $status_counts['cancelled'] }}</div>
            <div class="stat-label">Canceladas</div>
        </div>
    </div>

    @if ($format === 'full')
        {{-- Interviews Table --}}
        <h2 class="section-title">Detalle de Reservas</h2>
        <table>
            <thead>
                <tr>
                    <th style="width: 12%">Fecha</th>
                    <th style="width: 23%">Cliente</th>
                    <th style="width: 15%">Email</th>
                    <th style="width: 13%">Teléfono</th>
                    <th style="width: 15%">Evento</th>
                    <th style="width: 12%">Estado</th>
                    <th style="width: 10%">Items</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($interviews as $interview)
                    <tr>
                        <td>{{ $interview->created_at->format('d/m/Y') }}</td>
                        <td>{{ $interview->customer_name }}</td>
                        <td>{{ $interview->customer_email }}</td>
                        <td>{{ $interview->customer_phone ?? '-' }}</td>
                        <td>{{ $interview->event_date ? $interview->event_date->format('d/m/Y') : '-' }}</td>
                        <td>
                            <span class="badge badge-{{ $interview->status }}">
                                {{ ucfirst($interview->status) }}
                            </span>
                        </td>
                        <td>{{ $interview->items->count() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Page Break --}}
        <div class="page-break"></div>

        {{-- Top Products --}}
        <h2 class="section-title">Productos Más Solicitados</h2>
        <table>
            <thead>
                <tr>
                    <th style="width: 10%">#</th>
                    <th style="width: 60%">Producto</th>
                    <th style="width: 30%">Cantidad Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($top_products as $index => $product)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $product->name }}</td>
                        <td><strong>{{ $product->total }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Top Clients --}}
        <h2 class="section-title">Clientes Frecuentes</h2>
        <table>
            <thead>
                <tr>
                    <th style="width: 10%">#</th>
                    <th style="width: 40%">Cliente</th>
                    <th style="width: 30%">Email</th>
                    <th style="width: 20%">Reservas</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($top_clients as $index => $client)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $client->customer_name }}</td>
                        <td>{{ $client->customer_email }}</td>
                        <td><strong>{{ $client->total_bookings }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        {{-- Summary Format --}}
        <h2 class="section-title">Resumen Ejecutivo</h2>
        <table>
            <thead>
                <tr>
                    <th style="width: 50%">Métrica</th>
                    <th style="width: 50%">Valor</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Total de Solicitudes</td>
                    <td><strong>{{ $total_interviews }}</strong></td>
                </tr>
                <tr>
                    <td>Solicitudes Confirmadas</td>
                    <td><strong>{{ $status_counts['confirmed'] }}</strong></td>
                </tr>
                <tr>
                    <td>Solicitudes Pendientes</td>
                    <td><strong>{{ $status_counts['pending'] }}</strong></td>
                </tr>
                <tr>
                    <td>Solicitudes Canceladas</td>
                    <td><strong>{{ $status_counts['cancelled'] }}</strong></td>
                </tr>
                <tr>
                    <td>Solicitudes Completadas</td>
                    <td><strong>{{ $status_counts['completed'] }}</strong></td>
                </tr>
            </tbody>
        </table>
    @endif

    {{-- Footer --}}
    <div class="footer">
        © {{ date('Y') }} Recova Rentals - Reporte generado automáticamente
    </div>
</body>

</html>
