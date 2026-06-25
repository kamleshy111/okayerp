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
            [
                'name' => 'GST 0% (Exempt)',
                'rate' => 0.00,
                'cgst' => 0.00,
                'sgst' => 0.00,
                'igst' => 0.00,
                'is_active' => true,
            ],
            [
                'name' => 'GST 3%',
                'rate' => 3.00,
                'cgst' => 1.50,
                'sgst' => 1.50,
                'igst' => 3.00,
                'is_active' => true,
            ],
            [
                'name' => 'GST 5%',
                'rate' => 5.00,
                'cgst' => 2.50,
                'sgst' => 2.50,
                'igst' => 5.00,
                'is_active' => true,
            ],
            [
                'name' => 'GST 12%',
                'rate' => 12.00,
                'cgst' => 6.00,
                'sgst' => 6.00,
                'igst' => 12.00,
                'is_active' => true,
            ],
            [
                'name' => 'GST 18%',
                'rate' => 18.00,
                'cgst' => 9.00,
                'sgst' => 9.00,
                'igst' => 18.00,
                'is_active' => true,
            ],
            [
                'name' => 'GST 28%',
                'rate' => 28.00,
                'cgst' => 14.00,
                'sgst' => 14.00,
                'igst' => 28.00,
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
