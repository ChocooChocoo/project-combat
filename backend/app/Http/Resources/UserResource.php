<?php

namespace App\Http\Resources;

use App\Enums\GymPersonType;
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
            'last_name' => $this->last_name,
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
                    'permissions' => $this->resource->permissionsIn($gym)->values(),
                ];
            })->values(),
        ];
    }
}
