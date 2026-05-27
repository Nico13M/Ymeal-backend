<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

class AiRecipeService
{
    public function __construct(private HttpClientInterface $client)
    {
    }

    public function generateRecipe(array $payload): array
    {
        // !!!!!!!!!!!!!!!!!!!
        $pythonApiUrl = $_ENV['PYTHON_API_URL'] ?? 'http://127.0.0.1:8001/api/predict';

        try {
            $response = $this->client->request('POST', $pythonApiUrl, [
                'json' => $payload
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \Exception('Erreur de l\'API Python : ' . $response->getContent(false));
            }

            return $response->toArray();
            
        } catch (\Exception $e) {
            throw new \Exception("Impossible de joindre le générateur de recettes : " . $e->getMessage());
        }
    }
}