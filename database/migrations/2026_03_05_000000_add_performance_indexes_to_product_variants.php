<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            foreach ([
                'pv_rim_diameter_idx' => 'rim_diameter',
                'pv_rim_width_idx'    => 'rim_width',
                'pv_bolt_pattern_idx' => 'bolt_pattern',
                'pv_price_idx'        => 'uae_retail_price',
                'pv_clearance_idx'    => 'clearance_corner',
            ] as $name => $col) {
                if (!$this->hasIndex('product_variants', $name)) {
                    $table->index($col, $name);
                }
            }
        });

        Schema::table('products', function (Blueprint $table) {
            if (!$this->hasIndex('products', 'p_status_idx')) {
                $table->index('status', 'p_status_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            foreach (['pv_rim_diameter_idx','pv_rim_width_idx','pv_bolt_pattern_idx','pv_price_idx','pv_clearance_idx'] as $idx) {
                if ($this->hasIndex('product_variants', $idx)) $table->dropIndex($idx);
            }
        });
        Schema::table('products', function (Blueprint $table) {
            if ($this->hasIndex('products', 'p_status_idx')) $table->dropIndex('p_status_idx');
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        $conn = Schema::getConnection();

        if ($conn->getDriverName() === 'sqlite') {
            return false;
        }

        return (bool) $conn->select(
            "SELECT COUNT(1) as cnt FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?",
            [$conn->getDatabaseName(), $table, $index]
        )[0]->cnt;
    }
};
