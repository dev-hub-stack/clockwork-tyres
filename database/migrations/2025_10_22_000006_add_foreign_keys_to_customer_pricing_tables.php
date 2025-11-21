<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add foreign keys to customer_brand_pricing
        Schema::table('customer_brand_pricing', function (Blueprint $table) {
            if (!$this->foreignKeyExists('customer_brand_pricing', 'customer_brand_pricing_customer_id_foreign')) {
                $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            }
            if (!$this->foreignKeyExists('customer_brand_pricing', 'customer_brand_pricing_brand_id_foreign')) {
                $table->foreign('brand_id')->references('id')->on('brands')->onDelete('cascade');
            }
        });

        // Add foreign keys to customer_model_pricing
        Schema::table('customer_model_pricing', function (Blueprint $table) {
            if (!$this->foreignKeyExists('customer_model_pricing', 'customer_model_pricing_customer_id_foreign')) {
                $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            }
            if (!$this->foreignKeyExists('customer_model_pricing', 'customer_model_pricing_model_id_foreign')) {
                $table->foreign('model_id')->references('id')->on('models')->onDelete('cascade');
            }
        });

        // Add foreign keys to customer_addon_category_pricing
        Schema::table('customer_addon_category_pricing', function (Blueprint $table) {
            if (!$this->foreignKeyExists('customer_addon_category_pricing', 'customer_addon_category_pricing_customer_id_foreign')) {
                $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
            }
            // NOTE: add_on_category_id foreign key will be added after addon_categories table is created
            // This happens in a later migration (2025_10_24_000001)
        });
    }

    /**
     * Check if foreign key exists
     */
    private function foreignKeyExists(string $table, string $key): bool
    {
        $connection = Schema::getConnection();
        
        if ($connection->getDriverName() === 'sqlite') {
            return false;
        }

        $dbName = $connection->getDatabaseName();
        
        $result = $connection->select(
            "SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [$dbName, $table, $key]
        );
        
        return count($result) > 0;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_brand_pricing', function (Blueprint $table) {
            if ($this->foreignKeyExists('customer_brand_pricing', 'customer_brand_pricing_customer_id_foreign')) {
                $table->dropForeign(['customer_id']);
            }
            if ($this->foreignKeyExists('customer_brand_pricing', 'customer_brand_pricing_brand_id_foreign')) {
                $table->dropForeign(['brand_id']);
            }
        });

        Schema::table('customer_model_pricing', function (Blueprint $table) {
            if ($this->foreignKeyExists('customer_model_pricing', 'customer_model_pricing_customer_id_foreign')) {
                $table->dropForeign(['customer_id']);
            }
            if ($this->foreignKeyExists('customer_model_pricing', 'customer_model_pricing_model_id_foreign')) {
                $table->dropForeign(['model_id']);
            }
        });

        Schema::table('customer_addon_category_pricing', function (Blueprint $table) {
            if ($this->foreignKeyExists('customer_addon_category_pricing', 'customer_addon_category_pricing_customer_id_foreign')) {
                $table->dropForeign(['customer_id']);
            }
            if ($this->foreignKeyExists('customer_addon_category_pricing', 'customer_addon_category_pricing_add_on_category_id_foreign')) {
                $table->dropForeign(['add_on_category_id']);
            }
        });
    }
};
