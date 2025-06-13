<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;

class MailController extends AbstractController
{
    #[Route('/mail/send', name: 'mail_send', methods: ['POST'])]
    public function sendEmail(Request $request): JsonResponse
    {
        // Validar IP de origen
        $trustedProxies = explode(',', $_ENV['TRUSTED_PROXIES'] ?? '');
        $clientIp = $request->getClientIp();
        if (!in_array($clientIp, $trustedProxies)) {
            return new JsonResponse(['error' => 'Unauthorized IP: ' . $clientIp], 403);
        }

        // Obtener datos del JSON
        $data = json_decode($request->getContent(), true);
        if (empty($data['subject']) || empty($data['html']) || empty($data['from']) || empty($data['to'])) {
            return new JsonResponse(['error' => 'Missing required fields'], 400);
        }

        // Validar correos electrónicos
        if (!filter_var($data['from'], FILTER_VALIDATE_EMAIL) || !filter_var($data['to'], FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['error' => 'Invalid email address'], 400);
        }

        // Configurar transporte manualmente
        try {
            $dsn = 'smtp://carlos@softcodex.ch:D!685174168020ab@smtp.office365.com:587?encryption=starttls';
            $transport = Transport::fromDsn($dsn);
            $mailer = new Mailer($transport);

            $email = (new Email())
                ->from($data['from'])
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