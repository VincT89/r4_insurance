<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        /** Preserve existing accounts and load only the original document templates, pending review. */
        $this->call(InsuranceFormTemplateSeeder::class);
    }
}
