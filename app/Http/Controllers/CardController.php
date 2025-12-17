<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Card;
use App\Models\NfcInventory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str; 

class CardController extends Controller
{
    /**
     * 1. PUBLIC PROFILE VIEW (The most hit endpoint)
     * GET /cards/{id}  (No Auth needed usually, or specific public route)
     */
    public function show($id)
    {
        // Cache profile for 60 seconds to reduce DB load
        $card = Cache::remember("card_profile_{$id}", 60, function () use ($id) {
            return Card::where('id', $id)
                       ->with('user') // Load user specific data if needed
                       ->first();
        });

        if (!$card || $card->status !== 'ACTIVE') {
            return response()->json(['message' => 'Card is not active or not found'], 404);
        }

        // Increment Views/Taps (Async or separate job preferred)
        // Card::where('id', $id)->increment('contacts_count'); 

        return response()->json($card);
    }

    /**
     * 2. LIST MY CARDS
     * GET /api/cards/me
     */
public function myCards(Request $request)
    {
        $cards = Card::with('nfcTag') 
                    ->where('user_id', $request->user()->id)
                    ->get();

        return response()->json($cards);
    }

    /**
     * 3. UPDATE PROFILE (Bio, Links, Theme)
     * PUT /api/cards/{id}/profile
     */
    public function updateProfile(Request $request, $id)
    {
        $user = $request->user();
        $card = Card::where('id', $id)->firstOrFail();
        $inventory = NfcInventory::where('tag_id', $card->nfc_tag_id)->first();

        // 1. Authorization
        if ($card->user_id != $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // 2. Employee Check
        if ($card->type === 'EMPLOYEE') {
            if (!($card->settings['can_employee_edit'] ?? false)) {
                return response()->json(['message' => 'Editing disabled.'], 403);
            }
        }

        // 3. Validation
        $card_data = $request->validate([
            'full_name'    => 'nullable|string|max:255',
            'bio'          => 'nullable|string|max:1000',
            'position'     => 'nullable|string|max:100',
            'company_name' => 'nullable|string|max:100',
            'theme_id'     => 'in:MODERN,CLASSIC',
            'color_scheme' => 'nullable|string',
            'social_links' => 'nullable|array',
        ]);

        $inventory_data = $request->validate([
            // 'deliverd' => 'nullable', 
            'nfc_assigned_to_card' => 'nullable',
        ]);

        // 4. Update
        $card->update($card_data);

        if ($inventory) {
            $inventory->update($inventory_data);
        }

        return response()->json([
            'message' => 'Details updated successfully',
            'data' => $card,
            'inventory' => $inventory
        ]);
    }

    // Upload Image
    public function uploadCardImage(Request $request, $id)
    {
        $user = $request->user();
        $card = Card::where('id', $id)->firstOrFail();

        // 1. Authorization
        if ($card->user_id != $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // 2. Employee Check
        if ($card->type === 'EMPLOYEE') {
            if (!($card->settings['can_employee_edit'] ?? false)) {
                return response()->json(['message' => 'Editing disabled.'], 403);
            }
        }

        // 3. Validation
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
        ]);

        // 4. Handle File Upload
        if ($request->hasFile('image')) {
            
            // مسح الصورة القديمة (تنظيف)
            if ($card->profile_image && \Storage::disk('public')->exists($card->profile_image)) {
                \Storage::disk('public')->delete($card->profile_image);
            }

            // رفع الجديدة
            $path = $request->file('image')->store('avatars', 'public');

            // تحديث الداتا بيز
            $card->update(['profile_image' => $path]);

            // نرجع الرابط الجديد للفرونت عشان يعرضه فوراً
            // (بناءً على الـ Accessor اللي عملناه في الموديل)
            return response()->json([
                'message' => 'Image updated successfully',
                'image_url' => $card->profile_image_url 
            ]);
        }

        return response()->json(['message' => 'No file uploaded'], 400);
    }

    /**
     * 4. FREEZE CARD (Toggle Status)
     */
    public function freeze(Request $request, $id)
    {
        $user = $request->user();

        $card = Card::where('id', $id)
                    ->where('user_id', $user->id)
                    ->firstOrFail();

        if ($card->status === 'ACTIVE') {
            $card->update(['status' => 'FROZEN']);
            $message = 'Card has been frozen ❄️';
        } else {
            $card->update(['status' => 'ACTIVE']);
            $message = 'Card is active again 🔥';
        }

        return response()->json([
            'message' => $message, 
            'new_status' => $card->status
        ]);
    }

    /**
     * 5. CLAIM CARD (For Individuals)
     */
  
  public function claim(Request $request)
    {
        $request->validate([
            'full_name' => 'required|string',
            'nfc_tag_id' => 'required|exists:nfc_inventories,tag_id',
            'secret_key' => 'nullabe|string'
        ]);

        $tag = NfcInventory::where('tag_id', $request->nfc_tag_id)
                        //    ->where('secret_key', $request->secret_key)
                           ->first();

        if (!$tag) {
            return response()->json(['message' => 'Invalid Tag ID or Secret Key'], 404);
        }

        if ($tag->status !== 'IN_STOCK') {
            return response()->json(['message' => 'This card is already active or not available.'], 400);
        }

        $user = $request->user();

        $isFirstCard = !Card::where('user_id', $user->id)->exists();

        // Create Card

        $card = Card::create([
            'user_id' => $user->id,
            'nfc_tag_id' => $tag->tag_id,
            'full_name' => $request->full_name,
            'type' => 'PERSONAL',
            'status' => 'ACTIVE',
            
            'is_primary' => $isFirstCard,
            
            'settings' => ['can_employee_edit' => true],
            'social_links' => [],
            'theme_id' => 'MODERN'
        ]);

        $tag->update(['status' => 'ASSIGNED']);

        return response()->json([
            'message' => 'Card activated successfully', 
            'data' => $card
        ]);
    }

    /**
     * 6. SET AS PRIMARY
     * PATCH /api/cards/{id}/primary
     */
    public function setPrimary(Request $request, $id)
    {
        $user = $request->user();
        $card = Card::where('id', $id)->where('user_id', $user->id)->firstOrFail();

        if ($card->status !== 'ACTIVE') {
            return response()->json(['message' => 'Cannot set inactive card as primary'], 400);
        }

        // Transaction to ensure data integrity
        DB::transaction(function () use ($user, $id) {
            // Unset all other cards
            Card::where('user_id', $user->id)->update(['is_primary' => false]);
            
            // Set this one
            Card::where('id', $id)->update(['is_primary' => true]);
        });

        return response()->json(['message' => 'Card set as primary']);
    }
}