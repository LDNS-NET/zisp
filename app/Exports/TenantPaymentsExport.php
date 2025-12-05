<?php

namespace App\Exports;

use App\Models\Tenants\TenantPayment;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TenantPaymentsExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    /**
     * Return the collection of tenant payments
     */
    public function collection()
    {
        return TenantPayment::with('user')->latest()->get();
    }

    /**
     * Define the headings for the Excel file
     */
    public function headings(): array
    {
        return [
            'ID',
            'User',
            'Phone',
            'Receipt Number',
            'Amount',
            'Checked',
            'Paid At',
            'Disbursement Type',
            'Created At',
        ];
    }

    /**
     * Map the data for each row
     */
    public function map($payment): array
    {
        return [
            $payment->id,
            $payment->user_id === null ? 'Deleted User' : ($payment->user?->username ?? 'Unknown'),
            $payment->phone ?? ($payment->user?->phone ?? 'N/A'),
            $payment->receipt_number,
            $payment->amount,
            $payment->checked ? 'Yes' : 'No',
            $payment->paid_at?->format('Y-m-d H:i:s') ?? 'N/A',
            ucfirst($payment->disbursement_type ?? 'pending'),
            $payment->created_at?->format('Y-m-d H:i:s') ?? 'N/A',
        ];
    }

    /**
     * Apply styles to the worksheet
     */
    public function styles(Worksheet $sheet)
    {
        return [
            // Style the first row (headings)
            1 => ['font' => ['bold' => true]],
        ];
    }
}
