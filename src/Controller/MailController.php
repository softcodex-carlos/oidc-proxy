<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;

class MailController extends AbstractController
{
    #[Route('/mail/send', name: 'mail_send', methods: ['POST'])]
    public function sendEmail(Request $request, MailerInterface $mailer): JsonResponse
    {

        $dsn = $_ENV['MAILER_DSN'];
        $transport = Transport::fromDsn($dsn);
        $mailer = new Mailer($transport);

        // Validar IP de origen
        $trustedProxies = explode(',', $_ENV['TRUSTED_PROXIES'] ?? '');
        $clientIp = $request->getClientIp();
        if (!in_array($clientIp, $trustedProxies)) {
            return new JsonResponse(['error' => 'Unauthorized IP: ' . $clientIp], 403);
        }

        // Obtener datos del JSON
        $data = json_decode($request->getContent(), true);
        if (empty($data['subject']) || empty($data['html']) || empty($data['from']) || empty($data['to']) || empty($data['tenant_id'])) {
            return new JsonResponse(['error' => 'Missing required fields'], 400);
        }

        // Validar correos electrónicos
        if (!filter_var($data['from'], FILTER_VALIDATE_EMAIL) || !filter_var($data['to'], FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['error' => 'Invalid email address'], 400);
        }

        // Validar tenant_id (opcional)
        $validTenants = explode(',', $_ENV['VALID_TENANTS'] ?? '');
        if (!empty($validTenants) && !in_array($data['tenant_id'], $validTenants)) {
            return new JsonResponse(['error' => 'Invalid tenant_id'], 400);
        }

        // Configurar SMTP dinámicamente por tenant_id (ejemplo)
        $dsn = $_ENV['MAILER_DSN'];
        if ($data['tenant_id'] === 'tenant_123') {
            $dsn = 'smtp://' . $_ENV['CLIENT_ID'] . ':' . $_ENV['CLIENT_SECRET'] . '@smtp.example.com:587';
        }
        // Actualizar el transporte SMTP si es necesario (depende de la implementación)

        // Enviar correo
        try {
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