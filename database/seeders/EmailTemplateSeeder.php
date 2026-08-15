<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Seeds the single default email template (subject + HTML body, with
 * {{greeting}} and {{questions}} placeholders), matching the content
 * currently used in the legacy n8n "Generate Email from Approved" workflow.
 * Editable afterward via the Email Templates Filament resource.
 *
 * Idempotent: safe to re-run, keyed on name. Existing templates are never
 * overwritten, so production edits survive deploys.
 */
class EmailTemplateSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        EmailTemplate::query()->firstOrCreate(
            ['name' => 'Default'],
            [
                'subject' => 'Deutsches Edelsteinhaus Sachwerte - Ihre Webinarfrage',
                'body' => File::get(database_path('seeders/data/email_template_body.html')),
            ],
        );

        $this->command?->info('Seeded the default email template.');
    }
}
