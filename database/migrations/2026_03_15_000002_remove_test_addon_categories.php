<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Delete test/dummy addon categories (and their addons) that were created
     * during sync testing and are showing up on both the CRM and the frontend.
     */
    public function up(): void
    {
        // Find IDs of test categories by name pattern
        $testCategoryIds = DB::table('addon_categories')
            ->where('name', 'like', 'Test%')
            ->pluck('id');

        if ($testCategoryIds->isNotEmpty()) {
            // Delete addons belonging to these test categories
            DB::table('addons')
                ->whereIn('addon_category_id', $testCategoryIds)
                ->delete();

            // Delete the test categories themselves
            DB::table('addon_categories')
                ->whereIn('id', $testCategoryIds)
                ->delete();
        }
    }

    public function down(): void
    {
        // Test data — no rollback needed.
    }
};
