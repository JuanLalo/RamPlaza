<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Update products_per_page config for RAM Plaza API integration.
 *
 * Required by Muro Loco integration to fetch more than 4 products.
 *
 * @see WI #192
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('core_config')
            ->where('code', 'catalog.products.storefront.products_per_page')
            ->update(['value' => '12,24,36,48']);
    }

    public function down(): void
    {
        DB::table('core_config')
            ->where('code', 'catalog.products.storefront.products_per_page')
            ->update(['value' => '12']);
    }
};
