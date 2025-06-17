<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class MailController extends AbstractController
{
    private ValidatorInterface $validator;

    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
    }

    #[Route('/mail/send', name: 'mail_send', methods: ['POST'])]
    public function sendEmail(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validar JSON
        if (!$data) {
            return new JsonResponse([
                'status' => 'error',
                'code' => 400,
                'message' => 'JSON inválido o mal formado'
            ], 400);
        }

        // Validar campos requeridos
        $requiredFields = ['subject', 'html', 'from', 'to', 'accessToken', 'tenant_id'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return new JsonResponse([
                    'status' => 'error',
                    'code' => 400,
                    'message' => "El campo '$field' es requerido"
                ], 400);
            }
        }

        // Validar tenant_id
        if ($data['tenant_id'] !== ($_ENV['VALID_TENANT'] ?? $data['tenant_id'])) {
            return new JsonResponse([
                'status' => 'error',
                'code' => 403,
                'message' => 'Tenant ID no autorizado'
            ], 403);
        }

        // Validar correos
        $emailConstraint = new Assert\Email();
        $to = is_array($data['to']) ? $data['to'] : [$data['to']];
        foreach ([$data['from']] as $recipient) {
            $errors = $this->validator->validate($recipient, $emailConstraint);
            if (count($errors) > 0) {
                return new JsonResponse([
                    'status' => 'error',
                    'code' => 400,
                    'message' => "Formato de correo inválido en 'from': $recipient"
                ], 400);
            }
        }
        foreach ($to as $recipient) {
            $errors = $this->validator->validate($recipient, $emailConstraint);
            if (count($errors) > 0) {
                return new JsonResponse([
                    'status' => 'error',
                    'code' => 400,
                    'message' => "Formato de correo inválido en 'to': $recipient"
                ], 400);
            }
        }

        // Devolver los datos recibidos
        return new JsonResponse([
            'status' => 'success',
            'code' => 200,
            'message' => 'Datos recibidos correctamente',
            'data' => [
                'subject' => $data['subject'],
                'html' => $data['html'],
                'from' => $data['from'],
                'to' => $to,
                'tenant_id' => $data['tenant_id'],
                'accessToken' => substr($data['accessToken'], 0, 20) . '...' // Truncado por seguridad
            ]
        ], 200);
    }
}