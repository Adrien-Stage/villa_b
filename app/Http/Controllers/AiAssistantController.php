<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;

class AiAssistantController extends Controller
{
    public function chat(Request $request)
    {
        $request->validate([
            'messages' => 'required|array',
            'messages.*.role' => 'required|in:user,assistant',
            'messages.*.content' => 'required|string',
        ]);

        $user = Auth::user();
        $apiKey = config('services.mistral.key');
        $model = config('services.mistral.model', 'mistral-small-latest');

        if (empty($apiKey)) {
            return response()->json([
                'error' => 'La clé API Mistral n\'est pas configurée.'
            ], 500);
        }

        // Construction du contexte de base
        $systemPrompt = $this->buildSystemPrompt($user);

        // Préparation des messages
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt]
        ];

        // Ajouter l'historique envoyé par le client (max 10 derniers messages pour éviter de surcharger)
        $clientMessages = array_slice($request->messages, -10);
        foreach ($clientMessages as $msg) {
            $messages[] = [
                'role' => $msg['role'],
                'content' => $msg['content']
            ];
        }

        // Définition des outils
        $tools = $this->getToolsDefinition();

        $maxIterations = 5;
        $iteration = 0;
        $finalReply = 'Désolé, je n\'ai pas pu générer de réponse.';

        while ($iteration < $maxIterations) {
            $iteration++;
            
            $payload = [
                'model' => $model,
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 1000,
                'tools' => $tools,
                'tool_choice' => 'auto',
            ];

            try {
                $response = Http::withToken($apiKey)->timeout(45)->post('https://api.mistral.ai/v1/chat/completions',
                    $payload);
            } catch (\Exception $e) {
                return response()->json(['error' => 'Erreur de connexion: ' . $e->getMessage()], 500);
            }

            if (!$response->successful()) {
                return response()->json(['error' => 'Erreur API Mistral: ' . $response->body()], $response->status());
            }

            $data = $response->json();
            $message = $data['choices'][0]['message'] ?? null;
            
            if (!$message) break;

            // Ajouter la réponse (qui peut contenir des tool_calls) à l'historique
            $messages[] = $message;

            // S'il y a des appels d'outils, on les exécute
            if (isset($message['tool_calls']) && count($message['tool_calls']) > 0) {
                $aiTools = new \App\Services\AiToolsService();
                
                foreach ($message['tool_calls'] as $toolCall) {
                    $functionName = $toolCall['function']['name'];
                    $argumentsStr = $toolCall['function']['arguments'];
                    
                    // Mistral peut renvoyer une chaîne JSON ou directement un tableau selon le modèle, on sécurise
                    $arguments = is_string($argumentsStr) ? json_decode($argumentsStr, true) : $argumentsStr;
                    if (!is_array($arguments)) $arguments = [];
                    
                    $toolResult = "";
                    if (method_exists($aiTools, $functionName)) {
                        try {
                            $resultArray = call_user_func_array([$aiTools, $functionName], $arguments);
                            $toolResult = json_encode($resultArray, JSON_UNESCAPED_UNICODE);
                        } catch (\Exception $e) {
                            $toolResult = json_encode(['error' => $e->getMessage()]);
                        }
                    } else {
                        $toolResult = json_encode(['error' => 'Outil non trouvé']);
                    }

                    // Ajouter le résultat de l'outil pour Mistral
                    $messages[] = [
                        'role' => 'tool',
                        'name' => $functionName,
                        'content' => $toolResult,
                        'tool_call_id' => $toolCall['id']
                    ];
                }
                
                // On boucle pour renvoyer les résultats à Mistral
                continue;
            }

            // Si on arrive ici, c'est que Mistral a répondu du texte normal
            $finalReply = $message['content'];
            break;
        }

        return response()->json(['reply' => $finalReply]);
    }

    private function buildSystemPrompt($user)
    {
        $date = now()->locale('fr')->isoFormat('dddd D MMMM YYYY');
        $time = now()->format('H:i');

        $prompt = "Tu es Kuété, un Agent IA Autonome intégré au PMS de la Villa Boutanga.\n";
        $prompt .= "L'utilisateur avec qui tu parles s'appelle {$user->name} (Rôle: {$user->role}).\n";
        $prompt .= "Nous sommes le {$date}, et il est {$time}.\n\n";
        
        $prompt .= "Instructions STRICTES :\n";
        $prompt .= "- Tu disposes d'OUTILS (tools) pour interroger la base de données ou la mémoire.\n";
        $prompt .= "- N'invente JAMAIS d'informations (noms, chiffres, statuts). Si un utilisateur te demande une information sur l'hôtel, tu DOIS utiliser un outil pour vérifier.\n";
        $prompt .= "- Si l'utilisateur te demande de retenir ou mémoriser quelque chose, utilise l'outil `learn_fact`.\n";
        $prompt .= "- Si l'utilisateur pose une question nécessitant de se souvenir d'un fait passé (ex: 'Quel est le code wifi ?'), utilise l'outil `get_memories`.\n";
        $prompt .= "- Sois concis et direct dans tes réponses (2 à 4 phrases max).\n";
        $prompt .= "- Ne mentionne jamais explicitement que tu utilises des 'outils' ou des 'fonctions', agis naturellement.\n";

        return $prompt;
    }

    private function getToolsDefinition()
    {
        return [
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_hotel_stats',
                    'description' => 'Obtenir les statistiques actuelles de l\'hôtel (arrivées, départs, clients présents, chambres disponibles)',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object)[],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_housekeeping_status',
                    'description' => 'Obtenir le statut du housekeeping (chambres sales, en maintenance, et équipes actives/libres/occupées)',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object)[],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'learn_fact',
                    'description' => 'Mémoriser un fait important, une règle ou une consigne dans la mémoire à long terme',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'fact' => [
                                'type' => 'string',
                                'description' => 'Le fait ou la règle à mémoriser (ex: "Le code wifi est 1234")',
                            ],
                        ],
                        'required' => ['fact'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_memories',
                    'description' => 'Récupérer tous les faits et règles précédemment mémorisés dans la mémoire à long terme',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object)[],
                    ],
                ],
            ],
        ];
    }
}
