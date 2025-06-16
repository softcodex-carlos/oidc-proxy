<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MailController extends AbstractController
{
    private HttpClientInterface $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    #[Route('/mail/send', name: 'mail_send', methods: ['POST'])]
    public function sendEmail(Request $request): JsonResponse
    {
        $trustedProxies = explode(',', $_ENV['TRUSTED_PROXIES'] ?? '');
        $clientIp = $request->getClientIp();
        if (!in_array($clientIp, $trustedProxies)) {
            return new JsonResponse(['error' => 'Unauthorized IP: ' . $clientIp], 403);
        }

        $data = json_decode($request->getContent(), true);

        // Validar campos necesarios
        foreach (['subject', 'html', 'to', 'from', 'tenant_id', 'refreshToken'] as $field) {
            if (empty($data[$field])) {
                return new JsonResponse(['error' => "Missing required field: $field"], 400);
            }
        }

        $subject = $data['subject'];
        $html = $data['html'];
        $to = $data['to'];
        $fromEmail = $data['from'];
        $tenantId = $data['tenant_id'];
        $refreshToken = $data['refreshToken'];

        // Depurar datos recibidos
        error_log('MailController: from=' . $fromEmail . ', tenant_id=' . $tenantId . ', refresh_token=' . (strlen($refreshToken) > 10 ? 'present' : 'invalid'));

        $clientId = $_ENV['CLIENT_ID'];
        $clientSecret = $_ENV['CLIENT_SECRET'];

        try {
            // Obtener access_token desde el refresh_token
            $tokenResponse = $this->httpClient->request('POST', "https://login.microsoftonline.com/$tenantId/oauth2/v2.0/token", [
                'body' => [
                    'grant_type' => 'refresh_token',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'refresh_token' => $refreshToken,
                    'scope' => 'https://graph.microsoft.com/.default',
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ]
            ]);

            $tokenData = $tokenResponse->toArray();
            $accessToken = $tokenData['access_token'];

            // Enviar correo usando Microsoft Graph
            $emailPayload = [
                'message' => [
                    'subject' => $subject,
                    'body' => [
                        'contentType' => 'HTML',
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
                            'address' => $fromEmail,
                        ],
                    ],
                ],
                'saveToSentItems' => true,
            ];

            $response = $this->httpClient->request('POST', 'https://graph.microsoft.com/v1.0/me/sendMail', [
                'headers' => [
                    'Authorization' => "Bearer $accessToken",
                    'Content-Type' => 'application/json',
                ],
                'json' => $emailPayload,
            ]);

            if ($response->getStatusCode() === 202) {
                return new JsonResponse(['message' => 'Email sent successfully'], 200);
            } else {
                return new JsonResponse(['error' => 'Failed to send email'], 500);
            }
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Failed to send email: ' . $e->getMessage()], 500);
        }
    }
}