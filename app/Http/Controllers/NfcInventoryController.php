<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\NfcInventory;
use Illuminate\Validation\Rule;

class NfcInventoryController extends Controller
{
    /**
     * 1. List Inventory (With Filters)
     * GET /api/admin/inventory?status=IN_STOCK
     */
    public function index(Request $request)
    {
        $query = NfcInventory::query();

        // Filter by Status (IN_STOCK, ASSIGNED, BLACKLISTED)
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search by Batch ID
        if ($request->has('batch_id')) {
            $query->where('batch_id', $request->batch_id);
        }

        // Search by Tag ID
        if ($request->has('search')) {
            $query->where('tag_id', 'like', '%' . $request->search . '%');
        }

        return response()->json($query->paginate(20));
    }

    /**
     * 2. Create Single Tag
     * POST /api/admin/inventory
     */
    public function store(Request $request)
    {
        $request->validate([
            'tag_id' => 'required|string|max:100|unique:nfc_inventories,tag_id',
            'batch_id' => 'required|string|max:50',
            'secret_key' => 'nullable|string'
        ]);

        $tag = NfcInventory::create([
            'tag_id' => $request->tag_id,
            'batch_id' => $request->batch_id,
            'secret_key' => $request->secret_key,
            'status' => 'IN_STOCK'
        ]);

        return response()->json(['message' => 'Tag added successfully', 'data' => $tag], 201);
    }

    /**
     * 3. Bulk Generate (Bonus: For manufacturing) 🚀
     * POST /api/admin/inventory/generate
     * Generates sequential tags for a batch.
     */
    public function generateBatch(Request $request)
    {
        $request->validate([
            'batch_id' => 'required|string',
            'prefix' => 'required|string', // e.g., "TAP_2025_"
            'count' => 'required|integer|min:1|max:1000', // Limit to prevent timeout
            'start_sequence' => 'required|integer'
        ]);

        $createdCount = 0;
        $errors = [];

        for ($i = 0; $i < $request->count; $i++) {
            $sequence = $request->start_sequence + $i;
            $tagId = $request->prefix . str_pad($sequence, 6, '0', STR_PAD_LEFT); // e.g., TAP_2025_000001

            // Check duplicate to prevent crash
            if (NfcInventory::where('tag_id', $tagId)->exists()) {
                $errors[] = "$tagId already exists";
                continue;
            }

            NfcInventory::create([
                'tag_id' => $tagId,
                'batch_id' => $request->batch_id,
                'status' => 'IN_STOCK'
            ]);
            $createdCount++;
        }

        return response()->json([
            'message' => "Batch processing completed. Created: $createdCount tags.",
            'errors' => $errors
        ]);
    }

    /**
     * 4. Update Tag (Manual Status Change)
     * PUT /api/admin/inventory/{tag_id}
     */
    public function update(Request $request, $tagId)
    {
        $tag = NfcInventory::where('tag_id', $tagId)->firstOrFail();

        $request->validate([
            'status' => 'in:IN_STOCK,ASSIGNED,BLACKLISTED,DAMAGED',
            'secret_key' => 'nullable|string'
        ]);

        if ($request->status === 'IN_STOCK' && $tag->status === 'ASSIGNED') {
            return response()->json(['message' => 'Warning: This tag is currently assigned to a user.'], 400);
        }

        $tag->update($request->only(['status', 'secret_key']));

        return response()->json(['message' => 'Tag updated successfully', 'data' => $tag]);
    }

    /**
     * 5. Delete Tag (Only if not used)
     * DELETE /api/admin/inventory/{tag_id}
     */
    public function destroy($tagId)
    {
        $tag = NfcInventory::where('tag_id', $tagId)->firstOrFail();

        if ($tag->status === 'ASSIGNED') {
            return response()->json([
                'message' => 'Cannot delete an assigned tag. Please unassign or blacklist it first.'
            ], 400);
        }

        $tag->delete();

        return response()->json(['message' => 'Tag deleted successfully']);
    }
}