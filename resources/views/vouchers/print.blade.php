<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vouchers Print - {{ $businessName }}</title>
    <style>
        @page {
            size: A4;
            margin: 0;
        }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            margin: 0;
            padding: 20mm;
            background-color: #f3f4f6;
        }
        .container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            max-width: 100%;
        }
        .voucher-card {
            background: white;
            border: 1px dashed #d1d5db;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 130px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .tenant-name {
            font-size: 11px;
            font-weight: 800;
            color: #2563eb;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 2px;
        }
        .label {
            font-size: 8px;
            color: #6b7280;
            text-transform: uppercase;
            font-weight: 600;
        }
        .code-container {
            margin: 10px 0;
            padding: 8px 0;
            border-top: 1px solid #f3f4f6;
            border-bottom: 1px solid #f3f4f6;
        }
        .code-label {
            font-size: 7px;
            color: #9ca3af;
            margin-bottom: 2px;
        }
        .code {
            font-family: 'Courier New', Courier, monospace;
            font-size: 20px;
            font-weight: 900;
            color: #111827;
            letter-spacing: 1px;
        }
        .duration {
            font-size: 12px;
            font-weight: 700;
            color: #374151;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }
        .watermark {
            position: absolute;
            bottom: -10px;
            right: -10px;
            opacity: 0.05;
            transform: rotate(-15deg);
        }

        @media print {
            body {
                background-color: transparent !important;
                padding: 10mm !important;
            }
            .voucher-card {
                border: 1px dashed #000 !important;
                box-shadow: none !important;
                page-break-inside: avoid;
            }
            .tenant-name {
                color: #000 !important;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        @foreach($vouchers as $voucher)
            <div class="voucher-card">
                <div>
                    <div class="tenant-name">{{ $businessName }}</div>
                    <div class="label">Hotspot Internet Voucher</div>
                </div>
                
                <div class="code-container">
                    <div class="code-label">LOGIN CODE</div>
                    <div class="code">{{ $voucher->code }}</div>
                </div>
                
                <div class="duration">
                    <span>⏱</span>
                    {{ $voucher->package ? $voucher->package->duration_value . ' ' . $voucher->package->duration_unit : 'Active' }}
                </div>

                <div class="watermark">
                    <svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 12.55a11 11 0 0 1 14.08 0"></path>
                        <path d="M1.42 9a16 16 0 0 1 21.16 0"></path>
                        <path d="M8.53 16.11a6 6 0 0 1 6.95 0"></path>
                        <line x1="12" y1="20" x2="12.01" y2="20"></line>
                    </svg>
                </div>
            </div>
        @endforeach
    </div>

    <script>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        }
    </script>
</body>
</html>
