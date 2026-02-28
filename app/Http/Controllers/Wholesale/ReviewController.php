<?php

namespace App\Http\Controllers\Wholesale;

use App\Modules\Wholesale\Reviews\Models\ProductReview;
use Illuminate\Http\Request;

/**
 * Review Controller (Phase 5)
 *
 * Maps to Angular:
 *   addProductReview() → POST /api/product-review/add
 */
class ReviewController extends BaseWholesaleController
{
    /**
     * POST /api/product-review/add
     */
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'rating'     => 'required|integer|min:1|max:5',
            'review'     => 'nullable|string|max:1000',
        ]);

        $dealer = $this->dealer();

        $review = ProductReview::create([
            'dealer_id'  => $dealer->id,
            'product_id' => $request->product_id,
            'rating'     => $request->rating,
            'review'     => $request->review,
            'is_approved'=> false, // Needs admin approval before showing publicly
        ]);

        return $this->success(
            $review, 
            'Review submitted successfully. It will be visible once approved.'
        );
    }
}
