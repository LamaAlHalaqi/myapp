<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AgenciesTableSeeder extends Seeder
{
    public function run()
    {
        DB::table('agencies')->insert([
            ['name' => 'وزارة الداخلية'],
            ['name' => 'وزارة الصحة'],
            ['name' => 'البلدية'],
        ]);
    }
}
