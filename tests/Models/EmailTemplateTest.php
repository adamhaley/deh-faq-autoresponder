<?php

namespace Tests\Models;

use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(EmailTemplate::class)]
class EmailTemplateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_the_users_personal_template_when_one_exists(): void
    {
        $default = EmailTemplate::factory()->create();
        $user = User::factory()->create();
        $personal = EmailTemplate::factory()->create(['user_id' => $user->id]);

        $resolved = EmailTemplate::forUser($user->id);

        $this->assertTrue($resolved->is($personal));
        $this->assertFalse($resolved->is($default));
    }

    #[Test]
    public function it_falls_back_to_the_shared_default_when_the_user_has_no_personal_template(): void
    {
        $default = EmailTemplate::factory()->create();
        $user = User::factory()->create();

        $resolved = EmailTemplate::forUser($user->id);

        $this->assertTrue($resolved->is($default));
    }

    #[Test]
    public function it_falls_back_to_the_shared_default_for_a_null_user(): void
    {
        $default = EmailTemplate::factory()->create();

        $resolved = EmailTemplate::forUser(null);

        $this->assertTrue($resolved->is($default));
    }

    #[Test]
    public function it_returns_null_when_no_template_exists_at_all(): void
    {
        $this->assertNull(EmailTemplate::forUser(null));
    }
}
