<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Card;
use App\Models\Employee;
use App\Models\NfcInventory;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class CompanyCardController extends Controller
{
    /**
     * 1. ISSUE NEW CARD
     * Assign a physical NFC tag to an Employee.
     * POST /api/company/cards/issue
     */
    public function issue(Request $request)
    {
        $companyId = $request->user()->id; // Logged in Company

        $request->validate([
            'user_id' => [
                'required', 
                // Ensure user exists and belongs to this company (optional but safe)
                Rule::exists('employees', 'user_id')->where(function ($query) use ($companyId) {
                    return $query->where('company_id', $companyId);
                }),
            ],
            'nfc_tag_id' => [
                'required',
                'exists:nfc_inventories,tag_id', // Must exist in inventory
            ],
            'can_employee_edit' => 'boolean'
        ]);

        return DB::transaction(function () use ($request, $companyId) {
            
            // A. Check Inventory Status
            $tag = NfcInventory::where('tag_id', $request->nfc_tag_id)->lockForUpdate()->first();
            
            if ($tag->status !== 'IN_STOCK') {
                return response()->json(['message' => 'This NFC Tag is not available (Assigned or Blacklisted)'], 400);
            }

            // B. Create the Card
            $card = Card::create([
                'user_id' => $request->user_id,
                'company_id' => $companyId,
                'nfc_tag_id' => $request->nfc_tag_id,
                'type' => 'EMPLOYEE',
                'status' => 'ACTIVE',
                'is_primary' => true, // Default to primary if it's their first
                'settings' => ['can_employee_edit' => $request->can_employee_edit ?? false],
                'social_links' => [], // Empty init
                'theme_id' => 'MODERN',
                'color_scheme' => 'LIGHT'
            ]);

            // C. Update Inventory
            $tag->update(['status' => 'ASSIGNED']);

            return response()->json([
                'message' => 'Card issued successfully', 
                'data' => $card
            ], 201);
        });
    }

    /**
     * 2. REASSIGN CARD
     * Move an existing card from Employee A to Employee B.
     * POST /api/company/cards/reassign
     */
    public function reassign(Request $request)
    {
        $companyId = $request->user()->id;
        
        $request->validate([
            'card_id' => 'required',
         
            'new_user_id' => [
                'required', 
                // Ensure user exists and belongs to this company (optional but safe)
                Rule::exists('employees', 'user_id')->where(function ($query) use ($companyId) {
                    return $query->where('company_id', $companyId);
                }),
            ],
        ]);

        $card = Card::where('id', $request->card_id)
                    ->where('company_id', $companyId) // Security: Own cards only
                    ->firstOrFail();

        // Update Owner
        $card->update(['user_id' => $request->new_user_id]);

        return response()->json(['message' => 'Card reassigned to new employee successfully']);
    }

    /**
     * 3. SWAP PHYSICAL TAG (Lost Card Scenario)
     * Keep digital profile, change physical link.
     * POST /api/company/cards/swap
     */
    public function swap(Request $request)
    {
        $companyId = $request->user()->id;
        
        $request->validate([
            'card_id' => 'required',
            'new_nfc_tag_id' => 'required|exists:nfc_inventories,tag_id'
        ]);

        return DB::transaction(function () use ($request, $companyId) {
            $card = Card::where('id', $request->card_id)
                        ->where('company_id', $companyId)
                        ->firstOrFail();

            // Check New Tag Availability
            $newTag = NfcInventory::where('tag_id', $request->new_nfc_tag_id)->lockForUpdate()->first();
            if ($newTag->status !== 'IN_STOCK') {
                return response()->json(['message' => 'New tag is not in stock'], 400);
            }

            // Optional: Mark old tag as BLACKLISTED or IN_STOCK depending on policy
            if ($card->nfc_tag_id) {
                NfcInventory::where('tag_id', $card->nfc_tag_id)
                            ->update(['status' => 'BLACKLISTED']); // Assume lost
            }

            // Link New Tag
            $card->update(['nfc_tag_id' => $request->new_nfc_tag_id]);
            $newTag->update(['status' => 'ASSIGNED']);
            
            return response()->json(['message' => 'Physical card swapped successfully']);
        });
    }
}