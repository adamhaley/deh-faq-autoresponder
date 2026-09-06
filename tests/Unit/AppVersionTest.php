<?php

namespace Tests\Unit;

use App\Support\AppVersion;
use Tests\TestCase;

class AppVersionTest extends TestCase
{
    public function test_it_reads_the_version_directly_from_composer_json(): void
    {
        $composerJson = json_decode(file_get_contents(base_path('composer.json')), true);

        $this->assertSame($composerJson['version'], AppVersion::current());
    }
}
