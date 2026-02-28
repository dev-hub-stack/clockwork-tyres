<?php

namespace App\Http\Controllers\Wholesale;

use App\Modules\Customers\Models\AddressBook;
use Illuminate\Http\Request;

/**
 * Address Book Controller (Phase 5)
 *
 * Maps to Angular: 
 *   myAddressBook()        → GET /api/address-book/all
 *   addAddressBook()       → POST /api/address-book/add
 *   updateAddressBook()    → PUT /api/address-book/{id}/update
 *   deleteAddressBook()    → DELETE /api/address-book/{id}/delete
 */
class AddressBookController extends BaseWholesaleController
{
    /**
     * GET /api/address-book/all
     */
    public function index(Request $request)
    {
        $dealer = $this->dealer();
        $addresses = AddressBook::where('customer_id', $dealer->id)->get();

        return $this->success($addresses);
    }

    /**
     * POST /api/address-book/add
     */
    public function store(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
            'address'    => 'required|string',
            'city'       => 'required|string|max:100',
            'state'      => 'nullable|string|max:100',
            'country'    => 'required|string|max:100', // Note: CRM uses 'country' not country_id in AddressBook
            'zip'        => 'nullable|string|max:20',
            'phone_no'   => 'required|string|max:30',
        ]);

        $dealer = $this->dealer();

        $address = AddressBook::create(array_merge($request->all(), [
            'customer_id'  => $dealer->id,
            'address_type' => 2, // Default to Shipping (2) for wholesale
        ]));

        return $this->success($address, 'Address added successfully.');
    }

    /**
     * PUT /api/address-book/{id}/update
     */
    public function update(Request $request, int $id)
    {
        $dealer = $this->dealer();
        $address = AddressBook::where('customer_id', $dealer->id)->findOrFail($id);

        $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name'  => 'sometimes|string|max:100',
            'address'    => 'sometimes|string',
            'city'       => 'sometimes|string|max:100',
            'state'      => 'sometimes|nullable|string|max:100',
            'country'    => 'sometimes|string|max:100',
            'zip'        => 'sometimes|nullable|string|max:20',
            'phone_no'   => 'sometimes|string|max:30',
        ]);

        $address->update($request->all());

        return $this->success($address->fresh(), 'Address updated successfully.');
    }

    /**
     * DELETE /api/address-book/{id}/delete
     */
    public function destroy(Request $request, int $id)
    {
        $dealer = $this->dealer();
        $address = AddressBook::where('customer_id', $dealer->id)->findOrFail($id);

        $address->delete();

        return $this->success(null, 'Address deleted successfully.');
    }
}
