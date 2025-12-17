<?php

namespace App\Http\Controllers;

use App\Models\ProfileLink;
use App\Models\LinkType; // Import LinkType model
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProfileLinksController extends Controller
{
    /**
     * List all links for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        // Retrieve user links with their associated type (Eager Loading)
        $links = $request->user()->links()->with('type')->get();
        
        return response()->json(['data' => $links]);
    }

    /**
     * Store a new link for the authenticated user.
     */
    public function store(Request $request): JsonResponse
    {
        // 1. Validation
        $request->validate([
            'link_type_id' => ['required', 'exists:link_types,id'], // Must exist in link_types table
            'value'  => ['required', 'string'], // The input (username or phone number)
        ]);

        $user = $request->user();
        
        // 2. Retrieve the LinkType to check for Base URL logic
        $linkType = LinkType::find($request->link_type_id);

        // 3. Prepare the Final URL
        $finalUrl = $request->value;
        
        // If the type has a Base URL (e.g., https://facebook.com/) and input doesn't start with http, append it.
        if ($linkType->baseUrl && !str_starts_with($request->value, 'http')) {
             $finalUrl = $linkType->baseUrl . $request->value;
        }

        // 4. Create the link in the database
        $link = $user->links()->create([
            'link_type_id'  => $request->link_type_id,   // Mapping to your DB column
            'url' => $request->value,          // Mapping to your DB column
            'is_active' => true
        ]);

        return response()->json([
            'message' => 'Link added successfully',
            'data' => $link
        ], 201);
    }

    /**
     * Update an existing link.
     */
    public function update(Request $request, $id): JsonResponse
    {
        // Ensure the link belongs to the authenticated user
        $link = $request->user()->links()->findOrFail($id);

        $request->validate([
            'value' => ['sometimes', 'string'], // If user wants to change the value
            'is_active' => ['sometimes', 'boolean']
        ]);

        // Logic to update the URL if a new value is provided
        if ($request->has('value')) {
             $linkType = $link->type; // Get type via relationship
             $finalUrl = $request->value;
             
             // Re-apply Base URL logic
             if ($linkType->baseUrl && !str_starts_with($request->value, 'http')) {
                 $finalUrl = $linkType->baseUrl . $request->value;
             }
             
             $link->linkUrl = $finalUrl; // Update the correct column
        }

        if ($request->has('is_active')) {
            $link->is_active = $request->is_active;
        }

        $link->save();

        return response()->json(['message' => 'Link updated', 'data' => $link]);
    }

    /**
     * Delete a link.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        // Find and delete the link (scoped to the user for security)
        $request->user()->links()->findOrFail($id)->delete();
        
        return response()->json(['message' => 'Link deleted successfully']);
    }
}