<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Voucher Print - {{ $businessName }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=Roboto+Mono:wght@700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --accent: #f59e0b;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-500: #6b7280;
            --gray-800: #1f2937;
            --gray-900: #111827;
        }

        @page {
            size: A4;
            margin: 10mm;
        }

        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            background-color: var(--gray-100);
            color: var(--gray-900);
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* Toolbar */
        .no-print {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: white;
            border-bottom: 2px solid var(--primary);
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 1000;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .toolbar-title {
            font-weight: 800;
            color: var(--primary-dark);
            font-size: 18px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .toolbar-tip {
            font-size: 12px;
            color: var(--gray-500);
            font-style: italic;
        }

        .btn-group {
            display: flex;
            gap: 12px;
        }

        .btn {
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: transform 0.1s;
        }
        .btn:active { transform: scale(0.98); }
        .btn-primary { background: var(--primary); color: white; }
        .btn-success { background: #10b981; color: white; }
        .btn-danger { background: #ef4444; color: white; }

        /* Vouchers Container */
        .page-content {
            padding-top: 80px;
            display: flex;
            justify-content: center;
        }

        .container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 30px;
            width: 100%;
            max-width: 190mm; /* A4 width minus margins */
        }

        /* Voucher Card Design */
        .voucher-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            border: 1px solid var(--gray-200);
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            position: relative;
            aspect-ratio: 1.6 / 1;
            height: 125px;
        }

        .card-header {
            background: var(--primary);
            color: white;
            padding: 10px 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-business-name {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 120px;
        }

        .card-tag {
            background: rgba(255, 255, 255, 0.2);
            font-size: 8px;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .card-body {
            flex: 1;
            padding: 12px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
        }

        .card-label {
            font-size: 7px;
            color: var(--gray-500);
            font-weight: 700;
            text-transform: uppercase;
            margin-bottom: 4px;
        }

        .card-code {
            font-family: 'Roboto Mono', monospace;
            font-size: 22px;
            font-weight: 700;
            color: var(--gray-900);
            letter-spacing: 1px;
            background: var(--gray-100);
            padding: 4px 12px;
            border-radius: 6px;
            border: 1px solid var(--gray-200);
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.05);
        }

        .card-footer {
            padding: 8px 12px;
            background: #fafafa;
            border-top: 1px dashed var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-duration {
            font-size: 11px;
            font-weight: 700;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .card-info {
            font-size: 10px;
            font-weight: 600;
            color: var(--primary);
        }

        /* WiFi Icon Watermark */
        .wifi-icon {
            position: absolute;
            bottom: 30px;
            right: 12px;
            opacity: 0.03;
            width: 50px;
            pointer-events: none;
        }

        @media print {
            body {
                background: transparent !important;
                padding: 0 !important;
            }
            .page-content {
                padding-top: 0 !important;
            }
            .no-print {
                display: none !important;
            }
            .container {
                gap: 12mm; /* Substantial gap for cutting */
            }
            .voucher-card {
                box-shadow: none !important;
                border: 1px solid #000 !important;
                page-break-inside: avoid;
            }
            .card-header {
                background: var(--primary) !important;
            }
            .card-tag {
                background: rgba(255, 255, 255, 0.2) !important;
                border: 1px solid rgba(255, 255, 255, 0.3);
            }
            .card-code {
                background: #f0f0f0 !important;
                border: 1px solid #ccc !important;
                color: #000 !important;
            }
            .card-footer {
                background: #fff !important;
                border-top: 1px dashed #000 !important;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <div class="toolbar-title">
            <span>🎟️</span>
            <span>Voucher Print Center</span>
            <span class="toolbar-tip">(A4-Optimized Design)</span>
        </div>
        <div class="btn-group">
            <button onclick="window.print()" class="btn btn-primary">
                <span>🖨️</span> Print Vouchers
            </button>
            <button onclick="window.print()" class="btn btn-success">
                <span>📄</span> Save as PDF
            </button>
            <button onclick="window.close()" class="btn btn-danger">
                <span>✖</span> Close
            </button>
        </div>
    </div>

    <div class="page-content">
        <div class="container">
            @foreach($vouchers as $voucher)
                <div class="voucher-card">
                    <div class="card-header">
                        <div class="card-business-name">{{ $businessName }}</div>
                        <div class="card-tag">WIFI ACCESS</div>
                    </div>
                    
                    <div class="card-body">
                        <div class="card-label">ENTER ACCESS CODE</div>
                        <div class="card-code">{{ $voucher->code }}</div>
                        
                        <svg class="wifi-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12.55a11 11 0 0 1 14.08 0"></path>
                            <path d="M1.42 9a16 16 0 0 1 21.16 0"></path>
                            <path d="M8.53 16.11a6 6 0 0 1 6.95 0"></path>
                            <line x1="12" y1="20" x2="12.01" y2="20"></line>
                        </svg>
                    </div>
                    
                    <div class="card-footer">
                        <div class="card-duration">
                            <span style="font-size: 14px;">⏱</span>
                            {{ $voucher->package ? $voucher->package->duration_value . ' ' . $voucher->package->duration_unit : 'Active' }}
                        </div>
                        <div class="card-info">
                            Connect & Go
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</body>
</html>

