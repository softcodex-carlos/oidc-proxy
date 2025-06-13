<?php

namespace App\Service;

use Microsoft\Graph\Graph;
use Microsoft\Graph\Model;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpClient\HttpClient;

class EmailService
{
    private CacheItemPoolInterface $cache;

    public function __construct(CacheItemPoolInterface $cache)
    {
        $this->cache = $cache;
    }

    public function sendEmail(string $to, string $subject, string $html, string $from, string $tenantId, string $clientId, string $clientSecret): void
    {
        // Obtener token de acceso
        $accessToken = $this->getAccessToken($tenantId, $clientId, $clientSecret);
        if (!$accessToken) {
            throw new \Exception('Failed to obtain access token');
        }

        $graph = new Graph();
        $graph->setAccessToken($accessToken);

        // Construir el correo
        $message = [
            'message' => [
                'subject' => $subject,
                'body' => [
                    'contentType' => 'html',
                    'content' => $html,
                ],
                'toRecipients' => [
                    [
                        'emailAddress' => [
                            'address' => $to,
                        ],
                    ],
                ],
                'from' => [
                    'emailAddress' => [
                        'address' => $from,
                    ],
                ],
            ],
        ];

        // Enviar el correo
        try {
            $graph->createRequest('POST', "/v1.0/users/{$from}/sendMail")
                ->setReturnType(Model\Message::class)
                ->attachBody($message)
                ->execute();
        } catch (\Exception $e) {
            // Registrar el error en el sistema de logging del proxy
            // Por ejemplo: file_put_contents('log.txt', $e->getMessage(), FILE_APPEND);
            throw new \Exception('Failed to send email via Microsoft Graph: ' . $e->getMessage());
        }
    }

    private function getAccessToken(string $tenantId, string $clientId, string $clientSecret): ?string
    {
        // Verificar caché
        $cacheKey = 'ms_graph_token_' . md5($tenantId . $clientId);
        $cachedToken = $this->cache->getItem($cacheKey);
        if ($cachedToken->isHit() && $cachedToken->get()['expires_at'] > time()) {
            return $cachedToken->get()['access_token'];
        }

        // Solicitar nuevo token
        $client = HttpClient::create();
        $response = $client->request('POST', "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token", [
            'body' => [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'scope' => 'https://graph.microsoft.com/.default',
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            // Registrar error
            throw new \Exception('Failed to obtain access token: ' . $response->getContent(false));
        }

        $tokenData = $response->toArray();
        $accessToken = $tokenData['access_token'];
        $expiresIn = $tokenData['expires_in'];

        // Guardar en caché
        $cachedToken->set([
            'access_token' => $accessToken,
            'expires_at' => time() + $expiresIn - 60, // Margen de seguridad
        ]);
        $this->cache->save($cachedToken);

        return $accessToken;
    }
}