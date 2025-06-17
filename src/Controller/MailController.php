<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class MailController extends AbstractController
{
    #[Route('/mail/send', name: 'mail_send', methods: ['POST'])]
    public function sendEmail(Request $request, MailerInterface $mailer): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return new JsonResponse([
                'status' => 'error',
                'code' => 400,
                'message' => 'JSON inválido o mal formado: ' . json_last_error_msg()
            ], 400);
        }

        if (!isset($data['from'], $data['to'], $data['subject'], $data['html'])) {
            return new JsonResponse([
                'status' => 'error',
                'code' => 400,
                'message' => 'Faltan campos requeridos: from, to, subject, html'
            ], 400);
        }

        $email = (new Email())
            ->from($data['from'])
            ->to($data['to'])
            ->subject($data['subject'])
            ->html($data['html']);

        try {
            $mailer->send($email);

            return new JsonResponse([
                'status' => 'success',
                'code' => 200,
                'message' => 'Correo enviado correctamente'
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'status' => 'error',
                'code' => 500,
                'message' => 'Error al enviar el correo: ' . $e->getMessage()
            ], 500);
        }
    }
}
