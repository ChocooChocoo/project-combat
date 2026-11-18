<?php

namespace Tests\Feature;

use App\Enums\MembershipStatus;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\MembershipSetup;
use Tests\TestCase;

class GymMemberReviewTest extends TestCase
{
    use MembershipSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMembershipGym();
    }

    public function test_a_guest_can_view_the_gym_but_cannot_read_reviews(): void
    {
        $this->getJson("/api/gyms/{$this->gym->id}")
            ->assertOk()
            ->assertJsonMissingPath('gym.reviews')
            ->assertJsonMissingPath('gym.average_rating')
            ->assertJsonMissingPath('gym.rating_count');
        $this->getJson($this->url('reviews'))->assertUnauthorized();
    }

    public function test_only_member_accounts_can_read_reviews_of_approved_gyms(): void
    {
        $this->actingAs($this->owner)->getJson($this->url('reviews'))->assertForbidden();
        $this->actingAs(User::factory()->owner()->create(['is_platform_admin' => true]))
            ->getJson($this->url('reviews'))->assertForbidden();

        $draft = Gym::factory()->application($this->owner)->create();
        $this->actingAs($this->applicant)->getJson("/api/gyms/{$draft->id}/reviews")->assertNotFound();
    }

    public function test_a_member_can_read_without_joining_but_cannot_post(): void
    {
        $this->actingAs($this->applicant)->getJson($this->url('reviews'))
            ->assertOk()
            ->assertJsonPath('can_review', false)
            ->assertJsonPath('my_review', null)
            ->assertJsonPath('average_rating', null)
            ->assertJsonPath('rating_count', 0);
        $this->putJson($this->url('reviews/mine'), ['rating' => 4])->assertForbidden();
    }

    public function test_pending_membership_does_not_let_a_member_review(): void
    {
        $this->apply();

        $this->getJson($this->url('reviews'))->assertOk()->assertJsonPath('can_review', false);
        $this->putJson($this->url('reviews/mine'), ['rating' => 4])->assertForbidden();
    }

    public function test_active_member_can_rate_only_their_own_gym_and_update_without_duplicates(): void
    {
        $this->active();
        $otherGym = Gym::factory()->approved()->create();
        $this->actingAs($this->applicant)
            ->putJson("/api/gyms/{$otherGym->id}/reviews/mine", ['rating' => 5])->assertForbidden();
        $this->getJson($this->url('reviews'))->assertOk()->assertJsonPath('can_review', true);
        $this->putJson($this->url('reviews/mine'), ['rating' => 5, 'comment' => 'Helpful coaches.'])->assertNoContent();
        $this->putJson($this->url('reviews/mine'), ['rating' => 3])->assertNoContent();

        $this->getJson($this->url('reviews'))
            ->assertOk()
            ->assertJsonCount(1, 'reviews')
            ->assertJsonPath('rating_count', 1)
            ->assertJsonPath('my_review.rating', 3)
            ->assertJsonPath('reviews.0.comment', null);
        $this->getJson("/api/gyms/{$otherGym->id}/reviews")
            ->assertOk()->assertJsonCount(0, 'reviews')->assertJsonPath('rating_count', 0);
    }

    public function test_ratings_are_validated_before_saving(): void
    {
        $this->active();
        $this->actingAs($this->applicant)
            ->putJson($this->url('reviews/mine'), ['rating' => 6, 'comment' => str_repeat('a', 1001)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rating', 'comment']);
        $this->getJson($this->url('reviews'))->assertOk()->assertJsonPath('rating_count', 0);
    }

    public function test_reader_gets_newest_reviews_and_average_without_author_details(): void
    {
        $this->active();
        $this->actingAs($this->applicant)
            ->putJson($this->url('reviews/mine'), ['rating' => 5, 'comment' => 'Good coaching.'])->assertNoContent();

        $second = User::factory()->create();
        $this->apply($second)->forceFill(['status' => MembershipStatus::Active])->save();
        $this->actingAs($second)
            ->putJson($this->url('reviews/mine'), ['rating' => 2])->assertNoContent();

        $reader = User::factory()->create();
        $this->actingAs($reader)->getJson($this->url('reviews'))
            ->assertOk()
            ->assertJsonPath('rating_count', 2)
            ->assertJsonPath('average_rating', 3.5)
            ->assertJsonPath('reviews.0.rating', 2)
            ->assertJsonPath('reviews.1.comment', 'Good coaching.')
            ->assertJsonPath('my_review', null)
            ->assertJsonMissingPath('reviews.1.user_id')
            ->assertJsonMissingPath('reviews.1.user');

        $this->deleteJson($this->url('reviews/mine'), ['user_id' => $this->applicant->id])->assertNoContent();
        $this->getJson($this->url('reviews'))->assertOk()->assertJsonPath('rating_count', 2);
    }

    public function test_former_member_can_delete_but_cannot_edit_their_review(): void
    {
        $membership = $this->active();
        $this->actingAs($this->applicant)
            ->putJson($this->url('reviews/mine'), ['rating' => 4])->assertNoContent();
        $membership->forceFill(['status' => MembershipStatus::Expired])->save();

        $this->getJson($this->url('reviews'))
            ->assertOk()->assertJsonPath('can_review', false)->assertJsonPath('my_review.rating', 4);
        $this->putJson($this->url('reviews/mine'), ['rating' => 1])->assertForbidden();
        $this->deleteJson($this->url('reviews/mine'))->assertNoContent();
        $this->getJson($this->url('reviews'))->assertOk()->assertJsonPath('rating_count', 0);
    }
}
