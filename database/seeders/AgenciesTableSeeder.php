<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AgenciesTableSeeder extends Seeder
{
    public function run()
    {
        $agencies = [
            'وزارة الداخلية',
            'وزارة الصحة',
            'البلدية',
        ];

        foreach ($agencies as $name) {
            DB::table('agencies')->updateOrInsert(
                ['name' => $name],
                ['updated_at' => now(), 'created_at' => now()]
            );
        }
    }
}
