<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class MailController extends AbstractController
{
    private ValidatorInterface $validator;
    private HtmlSanitizer $sanitizer;

    public function __construct(ValidatorInterface $validator)
    {
        $this->validator = $validator;
        $config = (new HtmlSanitizerConfig())
            ->allowSafeElements() // Permite etiquetas seguras como <p>, <br>, <strong>, etc.
            ->allowStaticElements(); // Permite elementos estáticos como <img> con src seguro
        $this->sanitizer = new HtmlSanitizer($config);
    }

    private function configureTransport(string $accessToken, string $tenantId, string $username): Transport
    {
        $dsn = sprintf(
            '%s&username=%s&password=%s&tenant_id=%s',
            $_ENV['MAILER_DSN'],
            urlencode($username),
            urlencode($accessToken),
            urlencode($tenantId)
        );
        return Transport::fromDsn($dsn);
    }

    #[Route('/mail/send', name: 'mail_send', methods: ['POST'])]
    public function sendEmail(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'JSON inválido'], 400);
        }

        // Validar campos mínimos
        foreach (['subject', 'html', 'from', 'to', 'accessToken', 'tenant_id'] as $field) {
            if (empty($data[$field])) {
                return new JsonResponse(['error' => "Campo '$field' es requerido"], 400);
            }
        }

        // Validar tenant_id contra el permitido (opcional, elimina si aceptas múltiples tenants)
        if ($data['tenant_id'] !== $_ENV['VALID_TENANT']) {
            return new JsonResponse(['error' => 'Tenant ID no autorizado'], 403);
        }

        // Validar correos
        $emailConstraint = new Assert\Email();
        $to = is_array($data['to']) ? $data['to'] : [$data['to']];
        foreach ([$data['from']] as $recipient) {
            $errors = $this->validator->validate($recipient, $emailConstraint);
            if (count($errors) > 0) {
                return new JsonResponse(['error' => "Formato de correo inválido en 'from'"], 400);
            }
        }
        foreach ($to as $recipient) {
            $errors = $this->validator->validate($recipient, $emailConstraint);
            if (count($errors) > 0) {
                return new JsonResponse(['error' => "Formato de correo inválido en '$recipient'"], 400);
            }
        }

        try {
            $transport = $this->configureTransport($data['accessToken'], $data['tenant_id'], $data['from']);
            $mailer = new Mailer($transport);

            $email = (new Email())
                ->from($data['from'])
                ->to(...$to)
                ->subject($data['subject'])
                ->html($this->sanitizer->sanitize($data['html']));

            $mailer->send($email);

            return new JsonResponse(['status' => 'Correo enviado correctamente']);
        } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $e) {
            return new JsonResponse([
                'error' => 'Error de transporte (posiblemente token inválido)',
                'message' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Error general enviando correo',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}