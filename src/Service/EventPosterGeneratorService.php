<?php

namespace App\Service;

use App\Entity\Activity\Evenement;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EventPosterGeneratorService
{
    private HttpClientInterface $client;
    private string $replicateApiKey;

    public function __construct(HttpClientInterface $client)
    {
        $this->client = $client;
        $this->replicateApiKey = $_ENV['REPLICATE_API_KEY'] ?? '';
    }

    /**
     * Génère une affiche pour un événement avec Stable Diffusion
     */
    public function generatePoster(Evenement $evenement): array
    {
        if (!$this->replicateApiKey) {
            throw new \Exception("REPLICATE_API_KEY not configured");
        }

        // Construire le prompt basé sur les données de l'événement
        $prompt = $this->buildPromptFromEvent($evenement);

        try {
            // Lancer la génération (asynchrone)
            $response = $this->client->request(
                'POST',
                'https://api.replicate.com/v1/predictions',
                [
                    'headers' => [
                        'Authorization' => 'Token ' . $this->replicateApiKey,
                        'Content-Type' => 'application/json'
                    ],
                    'json' => [
                        'version' => 'db21e45d3f7023abc9e0ff587989658c92d6d40a90b63b4485399db8b34704c1',
                        'input' => [
                            'prompt' => $prompt,
                            'num_outputs' => 1,
                            'height' => 768,
                            'width' => 1024,
                            'scheduler' => 'K_EULER',
                            'num_inference_steps' => 25,
                            'guidance_scale' => 7.5
                        ]
                    ]
                ]
            );

            $data = $response->toArray();
            
            return [
                'status' => 'processing',
                'prediction_id' => $data['id'] ?? null,
                'created_at' => $data['created_at'] ?? null
            ];

        } catch (\Exception $e) {
            throw new \Exception("Erreur lors de la génération: " . $e->getMessage());
        }
    }

    /**
     * Vérifier l'état de la génération
     */
    public function checkPredictionStatus(string $predictionId): array
    {
        if (!$this->replicateApiKey) {
            throw new \Exception("REPLICATE_API_KEY not configured");
        }

        try {
            $response = $this->client->request(
                'GET',
                'https://api.replicate.com/v1/predictions/' . $predictionId,
                [
                    'headers' => [
                        'Authorization' => 'Token ' . $this->replicateApiKey
                    ]
                ]
            );

            $data = $response->toArray();

            return [
                'status' => $data['status'] ?? 'unknown',
                'output' => $data['output'] ?? null,
                'error' => $data['error'] ?? null
            ];

        } catch (\Exception $e) {
            throw new \Exception("Erreur lors de la vérification: " . $e->getMessage());
        }
    }

    /**
     * Construire le prompt basé sur les données de l'événement
     */
    private function buildPromptFromEvent(Evenement $evenement): string
    {
        $titre = $evenement->getTitre() ?? 'Événement Agricole';
        $date = $evenement->getDateDebut()?->format('d/m/Y') ?? 'Date TBD';
        $lieu = $evenement->getLieu() ?? 'Lieu à définir';
        $description = $evenement->getDescription() ?? 'Événement agricole professionnel';

        return <<<PROMPT
Créer une affiche professionnelle et attrayante pour un événement agricole avec:
- Titre: "$titre"
- Date: $date
- Lieu: $lieu
- Description: $description

Design requis:
- Couleurs vives et professionnelles (bleu, vert, or)
- Style moderne et épuré
- Icônes agricoles (tracteur, récolte, grains)
- Texte lisible et bien hiérarchisé
- Format paysage (1024x768)
- Arrière-plan avec motifs agricoles
- Style: affiche événementielle - haute qualité

Langue: Français
Ambiance: Professionnelle, moderne, inspirante
PROMPT;
    }
}
