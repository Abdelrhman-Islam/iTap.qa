<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Card;

class AdminCardController extends Controller
{
    /**
     * ADMIN ACTIONS ON CARDS
     * POST /api/admin/cards/{id}/action
     * Payload: { "action": "SUSPEND" | "BLOCK" | "REMOVE" }
     */
    public function action(Request $request, $id)
    {
        $request->validate([
            'action' => 'required|in:SUSPEND,BLOCK,REMOVE'
        ]);

        $card = Card::withTrashed()->findOrFail($id); // withTrashed to find even soft-deleted ones if needed

        switch ($request->action) {
            case 'SUSPEND':
                $card->update(['status' => 'SUSPENDED']);
                $message = 'Card has been suspended temporarily.';
                break;

            case 'BLOCK':
                $card->update(['status' => 'DEACTIVATED']);
                $message = 'Card has been permanently blocked.';
                break;

            case 'REMOVE':
                $card->delete(); 
                $message = 'Card has been removed (Soft Deleted).';
                break;
                
            default:
                return response()->json(['message' => 'Invalid action'], 400);
        }

        return response()->json([
            'message' => $message,
            'card_status' => $card->fresh()->status
        ]);
    }
}