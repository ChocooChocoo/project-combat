<?php

namespace App\Models;

use App\Enums\SupplierStatus;
use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** R-77: a recorded supplier for purchase orders. */
#[Fillable([
    'name', 'contact_person', 'phone', 'email', 'address', 'payment_terms', 'notes', 'status',
])]
class Supplier extends Model
{
    use BelongsToGym;

    protected $attributes = ['status' => 'active'];

    protected function casts(): array
    {
        return ['status' => SupplierStatus::class];
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function isActive(): bool
    {
        return $this->status === SupplierStatus::Active;
    }
}
