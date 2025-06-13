<?php

namespace App\Controller;

use App\Service\EmailService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class MailController extends AbstractController
{
    private EmailService $emailService;
    private ValidatorInterface $validator;
    private array $trustedProxies;

    public function __construct(EmailService $emailService, ValidatorInterface $validator, string $trustedProxies)
    {
        $this->emailService = $emailService;
        $this->validator = $validator;
        $this->trustedProxies = array_filter(array_map('trim', explode(',', $trustedProxies)));
    }

    /**
     * @Route("/api/mail/send", name="api_mail_send", methods={"POST"})
     */
    public function sendEmail(Request $request): JsonResponse
    {
        $clientIp = $request->getClientIp();
        $isTrusted = false;
        foreach ($this->trustedProxies as $proxy) {
            if ($this->isIpInRange($clientIp, $proxy)) {
                $isTrusted = true;
                break;
            }
        }

        if (!$isTrusted) {
            return new JsonResponse(['error' => 'Unauthorized IP address: ' . $clientIp], 403);
        }

        $data = json_decode($request->getContent(), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonResponse(['error' => 'Invalid JSON format'], 400);
        }

        $constraints = new Assert\Collection([
            'to' => [new Assert\NotBlank(), new Assert\Email()],
            'subject' => new Assert\NotBlank(),
            'html' => new Assert\NotBlank(),
            'from' => [new Assert\NotBlank(), new Assert\Email()],
            'tenant_id' => new Assert\NotBlank(),
        ]);

        $violations = $this->validator->validate($data, $constraints);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
            }
            return new JsonResponse(['error' => 'Validation failed', 'details' => $errors], 400);
        }

        try {
            $clientId = $_ENV['OIDC_CLIENT_ID'] ?? '';
            $clientSecret = $_ENV['OIDC_CLIENT_SECRET'] ?? '';
            if (empty($clientId) || empty($clientSecret)) {
                return new JsonResponse(['error' => 'Missing client_id or client_secret in proxy configuration'], 500);
            }

            $this->emailService->sendEmail(
                $data['to'],
                $data['subject'],
                $data['html'],
                $data['from'],
                $data['tenant_id'],
                $clientId,
                $clientSecret
            );

            return new JsonResponse(['message' => 'Email sent successfully'], 200);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Failed to send email: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Verifica si una IP está en un rango o coincide con una IP específica.
     */
    private function isIpInRange(string $ip, string $range): bool
    {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }

        [$subnet, $bits] = explode('/', $range);
        $ip = ip2long($ip);
        $subnet = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        return ($ip & $mask) === ($subnet & $mask);
    }
}