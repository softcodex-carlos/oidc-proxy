<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Symfony\Component\Mailer\Transport\Smtp\Auth\XOAuth2Authenticator;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;
use Symfony\Component\Mime\Email;
use League\OAuth2\Client\Token\AccessToken;

class MailController extends AbstractController
{
    #[Route('/mail/send', name: 'mail_send', methods: ['POST'])]
    public function sendEmail(Request $request): JsonResponse
    {
        // Obtener el contenido del body
        $data = json_decode($request->getContent(), true);

        // Validar que el JSON sea válido
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return new JsonResponse([
                'status' => 'error',
                'code' => 400,
                'message' => 'JSON inválido o mal formado: ' . json_last_error_msg()
            ], 400, [], false);
        }

        // Validar campos requeridos
        if (!isset($data['from'], $data['to'], $data['subject'], $data['html'], $data['accessToken'])) {
            return new JsonResponse([
                'status' => 'error',
                'code' => 400,
                'message' => 'Faltan campos requeridos: from, to, subject, html, accessToken'
            ], 400, [], false);
        }

        // Formar el DSN (sin contraseña, ya que usa OAuth2)
        $smtpHost = 'smtp.office365.com';
        $smtpPort = 587;
        $smtpUser = $data['from'];
        $dsn = sprintf('smtp://%s@%s:%d', urlencode($smtpUser), $smtpHost, $smtpPort);

        // Configurar el transporte con OAuth2
        try {
            $accessToken = new AccessToken(['access_token' => $data['accessToken']]);
            $authenticator = new XOAuth2Authenticator($smtpUser, $accessToken->getToken());
            $stream = new SocketStream();
            $stream->setHost($smtpHost);
            $stream->setPort($smtpPort);
            $transport = new SmtpTransport($stream, null, $authenticator);
            $mailer = new Mailer($transport);

            // Crear el correo
            $email = (new Email())
                ->from($data['from'])
                ->to($data['to'])
                ->subject($data['subject'])
                ->html($data['html']);

            // Enviar el correo
            $mailer->send($email);
        } catch (\Exception $e) {
            return new JsonResponse([
                'status' => 'error',
                'code' => 500,
                'message' => 'Error al enviar el correo: ' . $e->getMessage(),
                'dsn' => $dsn
            ], 500, [], false);
        }

        // Preparar la respuesta
        $response = [
            'status' => 'success',
            'code' => 200,
            'message' => 'Correo enviado correctamente',
            'data' => $data,
            'dsn' => $dsn
        ];

        // Truncar accessToken por seguridad
        if (isset($data['accessToken'])) {
            $response['data']['accessToken'] = substr($data['accessToken'], 0, 20) . '...';
        }

        return new JsonResponse($response, 200, [], false);
    }
}