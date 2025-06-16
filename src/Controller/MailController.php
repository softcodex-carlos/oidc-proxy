<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
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

        // Validar campos necesarios (sin refresh_token ni tenant_id, ya están en sesión o .env)
        foreach (['subject', 'html', 'to'] as $field) {
            if (empty($data[$field])) {
                return new JsonResponse(['error' => "Missing required field: $field"], 400);
            }
        }

        // Recuperar refresh_token y email de sesión
        $refreshToken = $request->getSession()->get('refresh_token');
        $fromEmail = $request->getSession()->get('user_email');

        if (!$refreshToken || !$fromEmail) {
            return new JsonResponse(['error' => 'Missing refresh token or user email in session'], 403);
        }

        $clientId = $_ENV['CLIENT_ID'];
        $clientSecret = $_ENV['CLIENT_SECRET'];
        $tenantId = $_ENV['TENANT_ID']; // puedes moverlo a .env, ya no debería venir del frontend

        try {
            // Obtener access_token desde el refresh_token
            $tokenResponse = $this->httpClient->request('POST', "https://login.microsoftonline.com/$tenantId/oauth2/v2.0/token", [
                'body' => [
                    'grant_type' => 'refresh_token',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'refresh_token' => $refreshToken,
                    'scope' => 'https://outlook.office365.com/.default',
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ]
            ]);

            $tokenData = $tokenResponse->toArray();
            $accessToken = $tokenData['access_token'];

            // Configurar DSN SMTP con XOAUTH2
            $dsn = sprintf(
                'smtp://%s@office365.com:587?auth=xoauth2&access_token=%s&encryption=starttls',
                urlencode($fromEmail),
                urlencode($accessToken)
            );

            $transport = Transport::fromDsn($dsn);
            $mailer = new Mailer($transport);

            $email = (new Email())
                ->from($fromEmail)
                ->to($data['to'])
                ->subject($data['subject'])
                ->html($data['html']);

            $mailer->send($email);

            return new JsonResponse(['message' => 'Email sent successfully'], 200);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Failed to send email: ' . $e->getMessage()], 500);
        }
    }
}
