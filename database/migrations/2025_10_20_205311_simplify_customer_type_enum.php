<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Simplify customer_type ENUM from ('retail', 'dealer', 'wholesale', 'corporate')
     * to just ('retail', 'dealer').
     * 
     * Rationale: 'dealer' IS a wholesaler, so 'wholesale' and 'corporate' are redundant.
     */
    public function up(): void
    {
        // Step 1: Update existing records - map 'wholesale' and 'corporate' to 'dealer'
        DB::table('customers')
            ->whereIn('customer_type', ['wholesale', 'corporate'])
            ->update(['customer_type' => 'dealer']);
        
        // Step 2: Modify the ENUM to only allow 'retail' and 'dealer'
        DB::statement("ALTER TABLE customers MODIFY customer_type ENUM('retail', 'dealer') NOT NULL DEFAULT 'retail'");
    }

    /**
     * Reverse the migrations.
     * 
     * Restore the original ENUM values
     */
    public function down(): void
    {
        // Restore original ENUM with all 4 values
        DB::statement("ALTER TABLE customers MODIFY customer_type ENUM('retail', 'dealer', 'wholesale', 'corporate') NOT NULL DEFAULT 'retail'");
    }
};
