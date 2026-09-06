<?php

namespace App\Support;

class AppVersion
{
    /**
     * The running application's version, read directly from composer.json's
     * root "version" field -- composer.json is deliberately the single
     * source of truth here, not Composer\InstalledVersions::getRootPackage(),
     * which resolves to the current VCS branch/tag (e.g. "dev-develop")
     * instead of the static version field whenever a .git directory is
     * present, making it unreliable for this purpose.
     */
    public static function current(): ?string
    {
        return once(function (): ?string {
            $composerJson = json_decode(file_get_contents(base_path('composer.json')), true);

            return $composerJson['version'] ?? null;
        });
    }
}
