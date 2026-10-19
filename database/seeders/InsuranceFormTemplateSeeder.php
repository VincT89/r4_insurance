<?php
namespace Database\Seeders;

use App\Models\InsuranceFormTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class InsuranceFormTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (json_decode(file_get_contents(resource_path('insurance/forms-source.json')),true,512,JSON_THROW_ON_ERROR) as $row) {
            InsuranceFormTemplate::firstOrCreate(['source_hash'=>$row['source_hash'],'source_sheet'=>$row['source_sheet'],'version'=>1],$row+['family'=>(string)Str::uuid()]);
        }
    }
}
