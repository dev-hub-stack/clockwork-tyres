<?php

namespace App\Http\Controllers\Wholesale;

use App\Modules\Wholesale\Pages\Models\HomeGallery;
use App\Modules\Products\Models\Brand;
use Illuminate\Http\Request;

/**
 * Homepage Controller (Phase 5)
 *
 * Maps to Angular:
 *   homepageGallery() → GET /api/gallery
 *   homepage()        → GET /api/homepage
 */
class HomepageController extends BaseWholesaleController
{
    /**
     * GET /api/gallery
     * Return active banner images for the homepage slider.
     */
    public function gallery(Request $request)
    {
        $galleries = HomeGallery::where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->get()
            ->map(fn($g) => [
                'id'         => $g->id,
                'title'      => $g->title,
                'image_url'  => $g->image_url, // Evaluates accessor
                'link'       => $g->link,
                'sort_order' => $g->sort_order,
            ]);

        return $this->success($galleries, 'Gallery images loaded.');
    }

    /**
     * GET /api/homepage
     * Return basic homepage data (featured brands, stats, etc.)
     */
    public function home(Request $request)
    {
        // For wholesale, we typically return a block of active brands
        $brands = Brand::where('is_active', true)
            ->orderBy('name')
            ->take(8)
            ->get()
            ->map(fn($b) => [
                'id'   => $b->id,
                'name' => $b->name,
                'slug' => $b->slug,
                'logo' => $b->logo_url ?? null,
            ]);

        // Add additional statistical blocks or promo data here if needed by Angular
        $homedata = [
            'featured_brands' => $brands,
        ];

        return $this->success($homedata, 'Homepage loaded.');
    }
}
