<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MailController extends AbstractController
{
    private $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    #[Route('/mail/test', name: 'mail_test', methods: ['GET'])]
    public function testMail(): Response
    {
        // Configuración fija para la prueba
        $accessToken = 'tu_access_token'; // Pega aquí el access_token que copiaste
        $to = 'carlos@softcodex.ch';
        $subject = 'prueba';
        $body = '<p>prueba2</p>'; // Envolvemos en <p> para formato HTML
        $from = 'carlos@softcodex.ch';

        try {
            // Construir el cuerpo del mensaje
            $message = [
                'subject' => $subject,
                'body' => [
                    'contentType' => 'HTML',
                    'content' => $body,
                ],
                'toRecipients' => [
                    ['emailAddress' => ['address' => $to]],
                ],
                'from' => [
                    'emailAddress' => ['address' => $from],
                ],
            ];

            // Enviar solicitud a Microsoft Graph
            $endpoint = "https://graph.microsoft.com/v1.0/users/{$from}/sendMail";
            $response = $this->httpClient->request('POST', $endpoint, [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => ['message' => $message],
            ]);

            // Verificar respuesta
            if ($response->getStatusCode() === 202) {
                return new Response('<h1>Correo enviado correctamente</h1>', 200, ['Content-Type' => 'text/html']);
            } else {
                return new JsonResponse(['error' => 'Failed to send test email: ' . $response->getContent(false)], $response->getStatusCode());
            }
        } catch (\Exception $e) {
            error_log('MailController test error: ' . $e->getMessage());
            return new JsonResponse(['error' => 'Failed to send test email: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/mail/send', name: 'mail_send', methods: ['POST'])]
    public function sendMail(Request $request): Response
    {
        // Obtener parámetros
        $accessToken = $request->request->get('access_token');
        $to = $request->request->get('to');
        $subject = $request->request->get('subject');
        $body = $request->request->get('body');
        $from = $request->request->get('from');
        $attachments = $request->files->all();

        // Validar parámetros requeridos
        if (!$accessToken || !$to || !$subject || !$body) {
            return new JsonResponse(['error' => 'Missing required parameters'], 400);
        }

        // Convertir destinatarios en array
        $toRecipients = array_filter(array_map('trim', explode(',', $to)));
        foreach ($toRecipients as $recipient) {
            if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                return new JsonResponse(['error' => "Invalid recipient email: {$recipient}"], 400);
            }
        }

        // Validar remitente
        if ($from && !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['error' => "Invalid sender email: {$from}"], 400);
        }

        // Validar dominios permitidos/prohibidos
        $clientConfig = $request->getSession()->get('client_config', []);
        $emailDomain = $from ? strtolower(substr(strrchr($from, '@'), 1)) : '';
        if ($emailDomain) {
            $excludedDomains = array_filter(array_map('trim', explode(',', $clientConfig['excluded_email_domains'] ?? '')));
            if (!empty($excludedDomains) && in_array($emailDomain, $excludedDomains)) {
                return new JsonResponse(['error' => 'Sender email domain not allowed'], 403);
            }
            $allowedDomains = array_filter(array_map('trim', explode(',', $clientConfig['allowed_email_domains'] ?? '')));
            if (!empty($allowedDomains) && !in_array($emailDomain, $allowedDomains)) {
                return new JsonResponse(['error' => 'Sender email domain not allowed'], 403);
            }
        }

        try {
            // Construir el cuerpo del mensaje
            $message = [
                'subject' => $subject,
                'body' => [
                    'contentType' => 'HTML',
                    'content' => $body,
                ],
                'toRecipients' => array_map(function ($recipient) {
                    return ['emailAddress' => ['address' => $recipient]];
                }, $toRecipients),
            ];

            // Configurar remitente
            if ($from) {
                $message['from'] = [
                    'emailAddress' => ['address' => $from],
                ];
            }

            // Manejar adjuntos
            if (!empty($attachments)) {
                $message['attachments'] = [];
                foreach ($attachments as $attachment) {
                    if ($attachment instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                        $message['attachments'][] = [
                            '@odata.type' => '#microsoft.graph.fileAttachment',
                            'name' => $attachment->getClientOriginalName(),
                            'contentType' => $attachment->getMimeType(),
                            'contentBytes' => base64_encode(file_get_contents($attachment->getPathname())),
                        ];
                    }
                }
            }

            // Enviar solicitud a Microsoft Graph
            $endpoint = $from ? "https://graph.microsoft.com/v1.0/users/{$from}/sendMail" : 'https://graph.microsoft.com/v1.0/me/sendMail';
            $response = $this->httpClient->request('POST', $endpoint, [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => ['message' => $message],
            ]);

            // Verificar respuesta
            if ($response->getStatusCode() === 202) {
                return new JsonResponse(['message' => 'Email sent successfully'], 202);
            } else {
                return new JsonResponse(['error' => 'Failed to send email: ' . $response->getContent(false)], $response->getStatusCode());
            }
        } catch (\Exception $e) {
            error_log('MailController error: ' . $e->getMessage());
            return new JsonResponse(['error' => 'Failed to send email: ' . $e->getMessage()], 500);
        }
    }
}