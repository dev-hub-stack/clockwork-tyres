<?php

namespace App\Http\Controllers\Wholesale;

use App\Modules\Wholesale\Pages\Models\WholesalePage;
use Illuminate\Http\Request;

/**
 * CMS Page Controller
 *
 * Maps to Angular:
 *   getPrivacyPolicy()      → GET /api/page/privacy-policy
 *   getFooterPage(pageUrl)  → GET /api/page/{slug}
 */
class PageController extends BaseWholesaleController
{
    /**
     * GET /api/page/{slug}
     */
    public function show(Request $request, string $slug)
    {
        $page = WholesalePage::where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (! $page) {
            // Return 404 gracefully
            return response()->json([
                'status'  => false,
                'message' => 'Page not found.',
                'data'    => null,
            ], 404);
        }

        return $this->success([
            'title'   => $page->title,
            'slug'    => $page->slug,
            'content' => $page->content, // HTML payload
        ]);
    }
}
