<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\ChatHistory;
use Illuminate\Support\Str;

class GuestChatbotController extends Controller
{
    public function guestchat(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
            'context' => 'nullable|array',
        ]);

        $apiUrl = env('AI_API_URL');
        if (!$apiUrl) {
            return response()->json(['error' => 'AI_API_URL is not set'], 500);
        }

        try {
            $response = Http::post($apiUrl, [
                'model' => 'mistral',
                'prompt' => $request->message,
                'context' => $request->context,
                'stream' => false,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to connect to AI API: ' . $e->getMessage()], 500);
        }

        $responseData = $response->json();

        $llmMessage = $responseData['response'] ?? 'No response from the model';
        $context = $responseData['context'] ?? [];
        
        return response()->json([
            'user_message' => $request->message,
            'chatbot_response' => $llmMessage,
            'context' => $context,
        ], 200);
    }
}
