<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Annotation\Route;

class MailController extends AbstractController
{
    private MailerInterface $mailer;

    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }

    #[Route('/mail/send', name: 'mail_send', methods: ['POST'])]
    public function sendEmail(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'JSON inválido'], 400);
        }

        // Validar campos mínimos
        foreach (['subject', 'html', 'from', 'to'] as $field) {
            if (empty($data[$field])) {
                return new JsonResponse(['error' => "Campo '$field' es requerido"], 400);
            }
        }

        try {
            $email = (new Email())
                ->from($data['from'])
                ->to($data['to'])
                ->subject($data['subject'])
                ->html($data['html']);

            $this->mailer->send($email);

            return new JsonResponse(['status' => 'Correo enviado correctamente']);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Error enviando correo',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
