<?php

namespace Database\Seeders;

use App\Models\Building;
use App\Models\VisitorPass;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([UserSeeder::class]);  
        $buildings = [
    ['code' => 'NW',  'name' => 'North Wing',        'color_name' => 'Red',    'color_hex' => '#dc2626', 'template_image' => 'images/passes/nw.png',  'qr_color_hex' => '#7f1d1d'],
    ['code' => 'SW',  'name' => 'South Wing',        'color_name' => 'Orange', 'color_hex' => '#ea580c', 'template_image' => 'images/passes/sw.png',  'qr_color_hex' => '#7c2d12'],
    ['code' => 'RVM', 'name' => 'RVM Building',      'color_name' => 'Green',  'color_hex' => '#15803d', 'template_image' => 'images/passes/rvm.png', 'qr_color_hex' => '#14532d'],
    ['code' => 'NG',  'name' => 'North Gate',        'color_name' => 'Pink',   'color_hex' => '#db2777', 'template_image' => 'images/passes/ng.png',  'qr_color_hex' => '#831843'],
    ['code' => 'MB',  'name' => 'Main Building',     'color_name' => 'Blue',   'color_hex' => '#2563eb', 'template_image' => 'images/passes/mb.png',  'qr_color_hex' => '#1e3a8a'],
    ['code' => 'SWA', 'name' => 'South Wing Annex',  'color_name' => 'Yellow', 'color_hex' => '#ca8a04', 'template_image' => 'images/passes/swa.png', 'qr_color_hex' => '#713f12'],
];

        foreach ($buildings as $b) {
            $building = Building::updateOrCreate(['code' => $b['code']], $b);

            if ($b['code'] === 'NG') {
                continue; // North Gate only ever holds multi-building passes
            }


            for ($i = 1; $i <= 5; $i++) {
                $passNumber = str_pad($i, 4, '0', STR_PAD_LEFT);
                $token = "HOR-20TH-{$b['code']}-{$passNumber}-SEC2026";
                
                VisitorPass::updateOrCreate(
                    ['building_id' => $building->id, 'pass_number' => $passNumber],
                    ['qr_token' => $token, 'status' => 'available']
                );
            }
        }
    }
}