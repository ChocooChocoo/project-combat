<?php

use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;

/**
 * Removes announcements.general.approve, decision_support.general.manage and
 * decision_support.general.approve. No route or screen ever checked them, so
 * granting them unlocked nothing. Role grants for them go with the rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        PermissionCatalog::sync();
    }

    public function down(): void
    {
        // Restoring the keys means putting them back in config/permissions.php and syncing again.
    }
};
