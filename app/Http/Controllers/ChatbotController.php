<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ChatbotController extends Controller
{
    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
        ]);

        // Send request to Ollama
        $response = Http::post(env('AI_API_URL'), [
            'model' => 'mistral', // Ensure 'mistral' is installed in Ollama
            'prompt' => $request->message,
            'stream' => false
        ]);

        // Extract response data
        $responseData = $response->json();
        $llmMessage = $responseData['response'] ?? 'No response from the model';

        return response()->json([
            'user_message' => $request->message,
            'chatbot_response' => $llmMessage
        ]);
    }
}
