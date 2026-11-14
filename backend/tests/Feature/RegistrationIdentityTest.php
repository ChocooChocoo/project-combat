<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** R-128, R-129: full name, birthdate and guardian at sign-up. */
class RegistrationIdentityTest extends TestCase
{
    use RefreshDatabase;

    private function register(array $extra = []): TestResponse
    {
        Notification::fake();

        return $this->postJson('/api/auth/register', [
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'email' => 'ana@example.com',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
            'account_type' => 'member',
            'birthdate' => '1995-05-10',
            ...$extra,
        ]);
    }

    private function guardian(): array
    {
        return ['guardian_name' => 'Rosa Reyes', 'guardian_phone' => '0917 123 4567', 'guardian_relationship' => 'Mother', 'guardian_consent' => '1'];
    }

    public function test_middle_name_and_suffix_are_saved_and_shown_in_full_name(): void
    {
        $this->register(['middle_name' => 'Maria', 'suffix' => 'Jr.'])->assertCreated();

        $user = User::firstWhere('email', 'ana@example.com');
        $this->actingAs($user)->getJson('/api/me')->assertOk()->assertJsonPath('data.full_name', 'Ana Maria Reyes Jr.');
    }

    public function test_bad_suffix_and_missing_birthdate_are_refused(): void
    {
        $this->register(['suffix' => 'Esq.'])->assertUnprocessable()->assertJsonValidationErrors('suffix');
        $this->register(['birthdate' => null])->assertUnprocessable()->assertJsonValidationErrors('birthdate');
    }

    public function test_children_under_seven_and_minor_owners_are_refused(): void
    {
        $this->register(['birthdate' => today()->subYears(6)->toDateString()])->assertUnprocessable()->assertJsonValidationErrors('birthdate');
        $this->register(['account_type' => 'owner', 'birthdate' => today()->subYears(17)->toDateString(), ...$this->guardian()])
            ->assertUnprocessable()->assertJsonValidationErrors('birthdate');
    }

    public function test_a_minor_member_needs_a_guardian_and_consent(): void
    {
        $this->register(['birthdate' => today()->subYears(12)->toDateString()])->assertUnprocessable()
            ->assertJsonValidationErrors(['guardian_name', 'guardian_phone', 'guardian_relationship', 'guardian_consent']);

        $this->register(['birthdate' => today()->subYears(12)->toDateString(), ...$this->guardian()])->assertCreated();

        $user = User::firstWhere('email', 'ana@example.com');
        $this->assertSame('Rosa Reyes', $user->guardian_name);
        $this->assertNotNull($user->guardian_consent_at);
    }

    public function test_an_adult_member_guardian_fields_are_dropped(): void
    {
        $this->register($this->guardian())->assertCreated();

        $user = User::firstWhere('email', 'ana@example.com');
        $this->assertNull($user->guardian_name);
        $this->assertNull($user->guardian_consent_at);
    }

    public function test_exactly_seven_is_accepted(): void
    {
        $this->register(['birthdate' => today()->subYears(7)->toDateString(), ...$this->guardian()])->assertCreated();
    }

    public function test_profile_saves_middle_name_suffix_city_and_province(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->putJson('/api/me', [
            'first_name' => 'Ana', 'last_name' => 'Reyes', 'email' => $user->email,
            'middle_name' => 'Maria', 'suffix' => 'III', 'city' => 'Silang', 'province' => 'Cavite',
        ])->assertOk();

        $user->refresh();
        $this->assertSame(['Maria', 'III', 'Silang', 'Cavite'], [$user->middle_name, $user->suffix, $user->city, $user->province]);
    }
}
