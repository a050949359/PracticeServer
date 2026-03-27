<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GoogleDriveOAuthConfigTest extends TestCase
{
    #[Test]
    public function google_drive_default_scope_contains_identity_and_drive_permissions(): void
    {
        config()->set('services.google_drive.scope', 'openid email profile https://www.googleapis.com/auth/drive.file');

        $scope = (string) config('services.google_drive.scope');

        $this->assertStringContainsString('openid', $scope);
        $this->assertStringContainsString('email', $scope);
        $this->assertStringContainsString('profile', $scope);
        $this->assertStringContainsString('https://www.googleapis.com/auth/drive.file', $scope);
    }
}
