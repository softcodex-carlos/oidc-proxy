<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MailController
{
    #[Route('/send-email', name: 'send_email', methods: ['POST'])]
    public function sendEmail(Request $request): Response
    {
        try {
            $data = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            return $this->jsonResponse([
                'status' => 'error',
                'message' => 'JSON mal formado: ' . $e->getMessage()
            ], 400);
        }

        $required = ['subject', 'html', 'from', 'to', 'accessToken'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->jsonResponse([
                    'status' => 'error',
                    'message' => "Falta el campo requerido: $field"
                ], 400);
            }
        }

        $emailPayload = [
            "message" => [
                "subject" => $data['subject'],
                "body" => [
                    "contentType" => "HTML",
                    "content" => $data['html']
                ],
                "toRecipients" => [
                    [
                        "emailAddress" => [
                            "address" => $data['to']
                        ]
                    ]
                ]
            ],
            "saveToSentItems" => "true"
        ];


        $url = "https://graph.microsoft.com/v1.0/me/sendMail";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($emailPayload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . $data['accessToken'],
                "Content-Type: application/json"
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode >= 200 && $httpCode < 300) {
            return $this->jsonResponse([
                'status' => 'success',
                'message' => 'Correo enviado correctamente.'
            ]);
        } else {
            return $this->jsonResponse([
                'status' => 'error',
                'message' => 'Error al enviar el correo',
                'httpCode' => $httpCode,
                'graphResponse' => json_decode($response, true),
                'curlError' => $error
            ], 500);
        }
    }

    private function jsonResponse(array $data, int $status = 200): Response
    {
        array_walk_recursive($data, function (&$value) {
            if (is_string($value)) {
                $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            }
        });

        $json = json_encode($data, JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            $json = json_encode([
                'status' => 'error',
                'message' => 'Error al codificar JSON: ' . json_last_error_msg()
            ]);
            $status = 500;
        }

        return new Response($json, $status, ['Content-Type' => 'application/json; charset=utf-8']);
    }
}
