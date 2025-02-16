<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Models\ChatHistory;
use Illuminate\Support\Str;

class ChatbotController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
            'session_id' => 'nullable|string',
        ]);

        $user = $request->user();
        $sessionId = $request->session_id ?? Str::uuid()->toString();

        $previousMessages = ChatHistory::where('user_id', $user->id)
            ->where('session_id', $sessionId)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($chat) => [
                ['role' => 'user', 'content' => $chat->user_message],
                ['role' => 'ollama', 'content' => $chat->bot_response],
                ['context' => $chat->context],
            ])
            ->flatten(1)
            ->toArray();


        $apiUrl = env('AI_API_URL');
        if (!$apiUrl) {
            return response()->json(['error' => 'AI_API_URL is not set'], 500);
        }

        try {
            $response = Http::post($apiUrl, [
                'model' => 'mistral',
                'prompt' => $request->message,
                'stream' => false,
                'context' => $previousMessages[count($previousMessages) - 1]['context'] ?? [],
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to connect to AI API: ' . $e->getMessage()], 500);
        }

        $responseData = $response->json();
        $llmMessage = $responseData['response'] ?? 'No response from the model';

        ChatHistory::create([
            'user_id' => $user->id,
            'session_id' => $sessionId,
            'user_message' => $request->message,
            'bot_response' => $llmMessage,
            'context' => $responseData['context'] ?? [],
        ]);

        $messageHistory = array_merge($previousMessages, [
            ['role' => 'user', 'content' => $request->message], 
            ['role' => 'ollama', 'content' => $llmMessage],
        ]);
        return response()->json([
            'user_message' => $request->message,
            'chatbot_response' => $llmMessage,
            'session_id' => $sessionId,
        ], 200);
    }
}
