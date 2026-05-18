<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SportsSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('sports')->insertOrIgnore([
            ['name' => 'running'],
            ['name' => 'cycling'],
            ['name' => 'swimming'],
            ['name' => 'triathlon'],
        ]);
    }
}
