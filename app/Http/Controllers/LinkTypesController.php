<?php

namespace App\Http\Controllers;

use App\Models\LinkType;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LinkTypesController extends Controller
{
    /**
     * List all link types.
     * Accessible by: Company, Employee, SuperAdmin.
     */
    public function index(): JsonResponse
    {
        // Group results by category for better frontend display
        $types = LinkType::all()->groupBy('category');
        return response()->json([
            'message' => 'Link types retrieved successfully',
            'data' => $types
        ]);
    }

    /**
     * Store new link types (Single or Bulk).
     * Accessible by: SuperAdmin ONLY 🛡️.
     */

    public function store(Request $request): JsonResponse
    {
        // 1. Security Check
        if ($request->user()->is_super_admin != 1) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        // 2. Handle Bulk Import (Array of Objects)
        if (isset($request[0]) && is_array($request[0])) {
            
            foreach ($request->all() as $item) {
                LinkType::firstOrCreate(
                    ['display' => $item['display']], 
                    [
                        'category' => $item['category'],
                        'icon'     => $item['icon'],
                        'base_url' => $item['base_url'] ?? $item['baseUrl'] ?? '', 
                    ]
                );
            }

            return response()->json(['message' => 'All Link Types imported successfully'], 201);
        }

        $request->validate([
            'display'  => ['required', 'string', 'unique:link_types,display'],
            'icon'     => ['required', 'string'],
            'category' => ['required', 'string'],
            'base_url' => ['nullable', 'string'],
        ]);

        $linkType = LinkType::create([
            'display'  => $request->display,
            'icon'     => $request->icon,
            'category' => $request->category,
            'base_url' => $request->base_url ?? $request->baseUrl ?? '',
        ]);

        return response()->json(['message' => 'Link Type created successfully', 'data' => $linkType], 201);
    }








    /**
     * Update an existing link type.
     * Accessible by: SuperAdmin ONLY 🛡️.
     */
    public function update(Request $request, $id): JsonResponse
    {
        // 1. Security Check
        if ($request->user()->type !== 'SuperAdmin') {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        $linkType = LinkType::findOrFail($id);

        $request->validate([
            'display'  => ['sometimes', 'string', 'unique:link_types,display,' . $id],
            'icon'     => ['sometimes', 'string'],
            'category' => ['sometimes', 'string'],
            'base_url'  => ['nullable', 'string'],
        ]);

        $linkType->update($request->all());

        return response()->json(['message' => 'Link Type updated successfully', 'data' => $linkType]);
    }

    /**
     * Delete a link type.
     * Accessible by: SuperAdmin ONLY 🛡️.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        // 1. Security Check
        if ($request->user()->type !== 'SuperAdmin') {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        LinkType::findOrFail($id)->delete();

        return response()->json(['message' => 'Link Type deleted successfully']);
    }
}