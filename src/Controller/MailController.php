<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Psr\Log\LoggerInterface;
use League\OAuth2\Client\Provider\GenericProvider;

class MailController extends AbstractController
{
    private $logger;
    private $oauthProvider;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->oauthProvider = new GenericProvider([
            'clientId'                => $_ENV['AZURE_CLIENT_ID'],
            'clientSecret'            => $_ENV['AZURE_CLIENT_SECRET'],
            'urlAuthorize'            => "https://login.microsoftonline.com/{$_ENV['AZURE_TENANT_ID']}/oauth2/v2.0/authorize",
            'urlAccessToken'          => "https://login.microsoftonline.com/{$_ENV['AZURE_TENANT_ID']}/oauth2/v2.0/token",
            'urlResourceOwnerDetails' => '',
            'scopes'                  => 'https://graph.microsoft.com/.default',
        ]);
    }

    #[Route('/mail/send', name: 'mail_send', methods: ['POST'])]
    public function send(Request $request): Response
    {
        // Obtener datos del cuerpo de la solicitud
        $data = json_decode($request->getContent(), true);
        $subject = $data['subject'] ?? null;
        $content = $data['content'] ?? null;
        $from = $data['from'] ?? null;
        $to = $data['to'] ?? [];

        // Validar parámetros requeridos
        if (!$subject || !$content || !$from || empty($to)) {
            $this->logger->warning('Missing required parameters', ['ip' => $request->getClientIp()]);
            return new Response('Missing required parameters: subject, content, from, or to', 400);
        }

        // Validar formato de correos
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
            $this->logger->warning('Invalid from email', ['from' => $from]);
            return new Response('Invalid from email address', 400);
        }

        foreach ($to as $recipient) {
            if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                $this->logger->warning('Invalid recipient email', ['recipient' => $recipient]);
                return new Response('Invalid recipient email address: ' . $recipient, 400);
            }
        }

        // Validar remitente autorizado
        $allowedSender = $_ENV['AZURE_ALLOWED_SENDER_EMAIL'] ?? null;
        if (!$allowedSender || $from !== $allowedSender) {
            $this->logger->warning('Unauthorized sender email', ['from' => $from]);
            return new Response('Unauthorized sender email', 403);
        }

        // Validar HTTPS en producción
        if (!$this->isSecureRequest($request)) {
            $this->logger->warning('Non-HTTPS request detected', ['ip' => $request->getClientIp()]);
            return new Response('HTTPS required', 403);
        }

        try {
            // Obtener token de acceso
            $token = $this->getAccessToken();

            // Enviar correo
            $this->sendEmail($token, $from, $to, $subject, $content);

            $this->logger->info('Email sent successfully', ['from' => $from, 'to' => $to]);
            return new Response('Email sent successfully', 200);
        } catch (\Exception $e) {
            $this->logger->error('Error sending email', ['error' => $e->getMessage(), 'from' => $from]);
            return new Response('Error sending email: ' . $e->getMessage(), 500);
        }
    }

    private function getAccessToken(): string
    {
        $cache = new FilesystemAdapter();
        $cacheKey = 'azure_access_token';
        $cachedToken = $cache->getItem($cacheKey);

        if ($cachedToken->isHit()) {
            return $cachedToken->get();
        }

        try {
            $accessToken = $this->oauthProvider->getAccessToken('client_credentials');
            $token = $accessToken->getToken();
            $expires = $accessToken->getExpires();

            $cachedToken->set($token);
            $cachedToken->expiresAt(new \DateTimeImmutable("@$expires"));
            $cache->save($cachedToken);

            return $token;
        } catch (\Exception $e) {
            $this->logger->error('Failed to obtain access token', ['error' => $e->getMessage()]);
            throw new \Exception('Failed to obtain access token');
        }
    }

    private function sendEmail(string $token, string $from, array $to, string $subject, string $content): void
    {
        $client = HttpClient::create();
        $recipients = array_map(function ($email) {
            return ['emailAddress' => ['address' => $email]];
        }, $to);

        $payload = [
            'message' => [
                'subject' => $subject,
                'body' => [
                    'contentType' => 'HTML',
                    'content' => $content,
                ],
                'toRecipients' => $recipients,
                'from' => [
                    'emailAddress' => [
                        'address' => $from,
                    ],
                ],
            ],
        ];

        $response = $client->request('POST', "https://graph.microsoft.com/v1.0/users/{$from}/sendMail", [
            'headers' => [
                'Authorization' => "Bearer {$token}",
                'Content-Type' => 'application/json',
            ],
            'json' => $payload,
        ]);

        if ($response->getStatusCode() !== 202) {
            $this->logger->error('Failed to send email', ['response' => $response->getContent(false)]);
            throw new \Exception('Failed to send email');
        }
    }

    private function isSecureRequest(Request $request): bool
    {
        if ($this->getParameter('kernel.environment') === 'dev') {
            return true;
        }
        return $request->isSecure();
    }
}