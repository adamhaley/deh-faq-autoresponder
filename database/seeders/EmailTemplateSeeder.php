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
 * Idempotent: safe to re-run, keyed on row existence rather than `name`.
 * `name` is a freely editable field in Filament, so it must never be the
 * idempotency key -- a rename would make firstOrCreate() blind to the
 * existing row and seed a duplicate on the next deploy.
 */
class EmailTemplateSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (EmailTemplate::query()->exists()) {
            return;
        }

        EmailTemplate::query()->create([
            'name' => 'Default',
            'subject' => 'Deutsches Edelsteinhaus Sachwerte - Ihre Webinarfrage',
            'body' => File::get(database_path('seeders/data/email_template_body.html')),
        ]);

        $this->command?->info('Seeded the default email template.');
    }
}
