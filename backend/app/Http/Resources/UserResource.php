<?php

namespace App\Http\Resources;

use App\Enums\GymPersonType;
use App\Support\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** What the screens need to know about the signed-in person. */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'suffix' => $this->suffix,
            'full_name' => $this->resource->fullName(),
            'birthdate' => $this->birthdate?->toDateString(),
            'city' => $this->city,
            'province' => $this->province,
            'email' => $this->email,
            'phone' => $this->phone,
            'photo_url' => $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null,
            'email_verified' => $this->email_verified_at !== null,
            'is_platform_admin' => $this->is_platform_admin,
            'password_change_required' => $this->password_change_required,
            'account_type' => $this->account_type?->value,
            'has_member_records' => $this->resource->hasMemberRecords(),
            'gyms' => $this->gyms->groupBy('id')->map(function ($rows) {
                $gym = $rows->first();
                $roles = $rows->pluck('pivot.role')->values();

                return [
                    'id' => $gym->id,
                    'name' => $gym->name,
                    'status' => $gym->status,
                    'roles' => $roles,
                    'role_names' => $this->resource->gymRoles($gym)->orderBy('name')->pluck('name')->values(),
                    'permissions' => $this->resource->permissionsIn($gym)->values(),
                    // R-106: why modules are missing, so screens can point the owner to My subscription.
                    'subscription' => (fn (array $state) => [
                        'status' => $state['status'],
                        'plan_name' => $state['plan']?->name,
                        'locked_modules' => $state['locked_modules'],
                        'period_ends_at' => Subscription::periodEnd($gym)?->toIso8601String(),
                    ])(Subscription::state($gym)),
                ];
            })->values(),
        ];
    }
}
