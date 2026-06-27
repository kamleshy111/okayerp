<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GstRate;

class GstRateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $gstRates = [
            // ── IGST rates (Interstate) ──────────────────────────
            [
                'name'      => '0% IGST',
                'rate'      => 0.00,
                'cgst'      => 0.00,
                'sgst'      => 0.00,
                'igst'      => 0.00,
                'is_active' => true,
            ],
            [
                'name'      => '3% IGST',
                'rate'      => 3.00,
                'cgst'      => 0.00,
                'sgst'      => 0.00,
                'igst'      => 3.00,
                'is_active' => true,
            ],
            [
                'name'      => '5% IGST',
                'rate'      => 5.00,
                'cgst'      => 0.00,
                'sgst'      => 0.00,
                'igst'      => 5.00,
                'is_active' => true,
            ],
            [
                'name'      => '9% IGST',
                'rate'      => 9.00,
                'cgst'      => 0.00,
                'sgst'      => 0.00,
                'igst'      => 9.00,
                'is_active' => true,
            ],
            [
                'name'      => '12% IGST',
                'rate'      => 12.00,
                'cgst'      => 0.00,
                'sgst'      => 0.00,
                'igst'      => 12.00,
                'is_active' => true,
            ],
            [
                'name'      => '18% IGST',
                'rate'      => 18.00,
                'cgst'      => 0.00,
                'sgst'      => 0.00,
                'igst'      => 18.00,
                'is_active' => true,
            ],
            [
                'name'      => '28% IGST',
                'rate'      => 28.00,
                'cgst'      => 0.00,
                'sgst'      => 0.00,
                'igst'      => 28.00,
                'is_active' => true,
            ],

            // ── CGST + SGST rates (Intrastate) ───────────────────
            [
                'name'      => '0% CGST+SGST',
                'rate'      => 0.00,
                'cgst'      => 0.00,
                'sgst'      => 0.00,
                'igst'      => 0.00,
                'is_active' => true,
            ],
            [
                'name'      => '3% CGST+SGST',
                'rate'      => 3.00,
                'cgst'      => 1.50,
                'sgst'      => 1.50,
                'igst'      => 0.00,
                'is_active' => true,
            ],
            [
                'name'      => '5% CGST+SGST',
                'rate'      => 5.00,
                'cgst'      => 2.50,
                'sgst'      => 2.50,
                'igst'      => 0.00,
                'is_active' => true,
            ],
            [
                'name'      => '9% CGST+SGST',
                'rate'      => 9.00,
                'cgst'      => 4.50,
                'sgst'      => 4.50,
                'igst'      => 0.00,
                'is_active' => true,
            ],
            [
                'name'      => '12% CGST+SGST',
                'rate'      => 12.00,
                'cgst'      => 6.00,
                'sgst'      => 6.00,
                'igst'      => 0.00,
                'is_active' => true,
            ],
            [
                'name'      => '18% CGST+SGST',
                'rate'      => 18.00,
                'cgst'      => 9.00,
                'sgst'      => 9.00,
                'igst'      => 0.00,
                'is_active' => true,
            ],
            [
                'name'      => '28% CGST+SGST',
                'rate'      => 28.00,
                'cgst'      => 14.00,
                'sgst'      => 14.00,
                'igst'      => 0.00,
                'is_active' => true,
            ],
        ];

        foreach ($gstRates as $rate) {
            GstRate::updateOrCreate(
                ['name' => $rate['name']],
                $rate
            );
        }
    }
}
