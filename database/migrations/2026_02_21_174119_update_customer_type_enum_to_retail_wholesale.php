<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Expand ENUM to include wholesale alongside existing values
        DB::statement("ALTER TABLE customers MODIFY COLUMN customer_type ENUM('retail', 'dealer', 'wholesale', 'corporate') NOT NULL DEFAULT 'retail'");

        // Step 2: Migrate dealer and corporate → wholesale
        DB::statement("UPDATE customers SET customer_type = 'wholesale' WHERE customer_type IN ('dealer', 'corporate')");

        // Step 3: Narrow ENUM to only retail and wholesale
        DB::statement("ALTER TABLE customers MODIFY COLUMN customer_type ENUM('retail', 'wholesale') NOT NULL DEFAULT 'retail'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE customers MODIFY COLUMN customer_type ENUM('retail', 'dealer', 'wholesale', 'corporate') NOT NULL DEFAULT 'retail'");
    }
};
