<?php

namespace Tests\Feature;

use App\Enums\GymPersonType;
use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\ProcurementSetup;
use Tests\TestCase;

/** R-113 editing a pending purchase request; R-114 receipt proof files on purchase orders. */
class PurchaseProofTest extends TestCase
{
    use ProcurementSetup, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpProcurementGym();
    }

    public function test_submitting_tells_approvers_and_the_requester_can_edit_lines_while_pending(): void
    {
        $second = $this->openItem(['name' => 'Gloves 12 oz', 'category' => 'equipment', 'unit' => 'pair']);
        $id = $this->actingAs($this->requester)->postJson($this->url('purchase-requests'), [
            'lines' => [['stock_item_id' => $this->item->id, 'quantity' => 2], ['stock_item_id' => $second->id, 'quantity' => 3]],
        ])->assertCreated()->assertJsonCount(2, 'request.lines')->json('request.id');

        $this->assertDatabaseHas('notifications', ['notifiable_id' => $this->approver->id]);
        $this->assertDatabaseMissing('notifications', ['notifiable_id' => $this->requester->id]);

        $this->actingAs($this->requester)->putJson($this->url("purchase-requests/{$id}"), [
            'note' => 'Changed', 'lines' => [['stock_item_id' => $second->id, 'quantity' => 5]],
        ])->assertOk()->assertJsonCount(1, 'request.lines')->assertJsonPath('request.note', 'Changed');

        $other = User::factory()->create();
        $this->gym->addPerson($other, GymPersonType::Employee);
        $this->giveProcurementRole($other, ['procurement.requests.view', 'procurement.requests.manage'], 'Clerk');
        $this->actingAs($other)->putJson($this->url("purchase-requests/{$id}"), ['lines' => [['stock_item_id' => $second->id, 'quantity' => 1]]])->assertForbidden();

        $this->approve($this->gym->purchaseRequests()->findOrFail($id));
        $this->actingAs($this->requester)->putJson($this->url("purchase-requests/{$id}"), ['lines' => [['stock_item_id' => $second->id, 'quantity' => 1]]])->assertConflict();
    }

    public function test_many_receipt_photos_upload_and_are_served(): void
    {
        Storage::fake('local');
        $order = $this->makeOrder($this->approve($this->makeRequest()));

        $proofs = $this->actingAs($this->approver)->post($this->url("purchase-orders/{$order->id}/proofs"), [
            'files' => [UploadedFile::fake()->create('receipt.jpg', 50, 'image/jpeg'), UploadedFile::fake()->create('box.png', 50, 'image/png'), UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf')],
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonCount(3, 'proofs')->json('proofs');

        $this->assertTrue($proofs[0]['is_image']);
        $this->actingAs($this->requester)->get($this->url("purchase-orders/{$order->id}/proofs/{$proofs[2]['id']}"))->assertOk();
        $this->actingAs($this->requester)->getJson($this->url("purchase-orders/{$order->id}"))->assertOk();

        $this->actingAs($this->approver)->deleteJson($this->url("purchase-orders/{$order->id}/proofs/{$proofs[0]['id']}"))->assertOk()->assertJsonCount(2, 'proofs');
    }

    public function test_wrong_type_permission_and_other_gym_are_refused(): void
    {
        Storage::fake('local');
        $order = $this->makeOrder($this->approve($this->makeRequest()));

        $this->actingAs($this->approver)->post($this->url("purchase-orders/{$order->id}/proofs"), [
            'files' => [UploadedFile::fake()->create('notes.txt', 10, 'text/plain')],
        ], ['Accept' => 'application/json'])->assertUnprocessable();

        // The requester can view orders but not record receipts.
        $this->actingAs($this->requester)->post($this->url("purchase-orders/{$order->id}/proofs"), [
            'files' => [UploadedFile::fake()->create('receipt.jpg', 50, 'image/jpeg')],
        ], ['Accept' => 'application/json'])->assertForbidden();

        $otherGym = Gym::factory()->approved()->create();
        $otherGym->addPerson($this->owner, GymPersonType::Owner);
        $this->actingAs($this->owner)->post("/api/gyms/{$otherGym->id}/purchase-orders/{$order->id}/proofs", [
            'files' => [UploadedFile::fake()->create('receipt.jpg', 50, 'image/jpeg')],
        ], ['Accept' => 'application/json'])->assertNotFound();
    }
}
