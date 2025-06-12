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
        $accessToken = 'eyJ0eXAiOiJKV1QiLCJub25jZSI6InpFTVozTmU5aHA0S3hCQUlNaWxkdVp6R2JNYWJidm5MMHhEbmp6Q1NxZ1UiLCJhbGciOiJSUzI1NiIsIng1dCI6IkNOdjBPSTNSd3FsSEZFVm5hb01Bc2hDSDJYRSIsImtpZCI6IkNOdjBPSTNSd3FsSEZFVm5hb01Bc2hDSDJYRSJ9.eyJhdWQiOiJodHRwczovL2dyYXBoLm1pY3Jvc29mdC5jb20iLCJpc3MiOiJodHRwczovL3N0cy53aW5kb3dzLm5ldC8xMzc0Mjk3My0wZWZiLTQ2MTktOTZhNi00OWYxNzk3OTU3ZTMvIiwiaWF0IjoxNzQ5NzM2NzU0LCJuYmYiOjE3NDk3MzY3NTQsImV4cCI6MTc0OTc0MDY1NCwiYWlvIjoiazJSZ1lOZzYwK3ZjM0tpd1dlZVlKd3JwSFZza0RnQT0iLCJhcHBfZGlzcGxheW5hbWUiOiJNYWdSZW50YWxfT0lEQyIsImFwcGlkIjoiYjNhYjg1YzQtNDliYS00ZDNmLTkyODMtMzkzYzJiZThkMmE2IiwiYXBwaWRhY3IiOiIxIiwiaWRwIjoiaHR0cHM6Ly9zdHMud2luZG93cy5uZXQvMTM3NDI5NzMtMGVmYi00NjE5LTk2YTYtNDlmMTc5Nzk1N2UzLyIsImlkdHlwIjoiYXBwIiwib2lkIjoiZTZiZGRmNTQtNDJjMS00ODQzLTk3MmUtN2MwMDJjNTcxNjFmIiwicmgiOiIxLkFWMEFjeWwwRV9zT0dVYVdwa254ZVhsWDR3TUFBQUFBQUFBQXdBQUFBQUFBQUFBS0FRQmRBQS4iLCJzdWIiOiJlNmJkZGY1NC00MmMxLTQ4NDMtOTcyZS03YzAwMmM1NzE2MWYiLCJ0ZW5hbnRfcmVnaW9uX3Njb3BlIjoiRVUiLCJ0aWQiOiIxMzc0Mjk3My0wZWZiLTQ2MTktOTZhNi00OWYxNzk3OTU3ZTMiLCJ1dGkiOiJKcVVFVmdaNXYwS2dUeEV6cGk4YkFBIiwidmVyIjoiMS4wIiwid2lkcyI6WyIwOTk3YTFkMC0wZDFkLTRhY2ItYjQwOC1kNWNhNzMxMjFlOTAiXSwieG1zX2Z0ZCI6IkpQRUtrNXRKLUNlZmhhczJDMUp6SkxRQk4xNlRpSUp5bTNsczk0QXZFSllCWlhWeWIzQmxkMlZ6ZEMxa2MyMXoiLCJ4bXNfaWRyZWwiOiIyMCA3IiwieG1zX3JkIjoiMC40MkxsWUJKaWpCVVM0V0FYRXREbmQyUHprenJndVBXSjBLb05jMVl5QUVVNWhRU0VWOVJtUl9Hdjg5eVJmZnhVLWY2RElMVWNRZ0xNREJCd0FFb0RBQSIsInhtc190Y2R0IjoxNTY5NTAxOTIwLCJ4bXNfdGRiciI6IkVVIn0.PDXL8lvGP51YU4YU4t7scXsEQ_hhVxSDTfvEDOKtpi0DZvyBBBsVy9w3OJVlgSLyvD3FeXKhOKf413yNl099qd2D7F73csNeEBo8SfdMCtoVR9mP89HLadUDc45YbxxXTmwuujzJXWZ_D3c8yANJENzkNG0Kjow2hTuDBNNiFPRRP1Yq4aKnx6Cb6V7qJhFRTdnO6edPga_K_czvxY9khecgdkUNXuzDw9Oi6pX6EWowy4wJ_BoRb-o5QwL1bSzafdQGJfQGT0MnkiekwL-0jYvgmIlrvbkQxwTJcvfFE1nCAdyy2fJlrDhDmfg_vBr3fL1gXICeYTIe8P-fREjm6A';

        $to = 'carlos@softcodex.ch';
        $subject = 'Prueba de correo';
        $body = 'Este es un correo de prueba desde la API de Microsoft Graph.';
        $from = 'carlos@softcodex.ch';
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

        $endpoint = "https://graph.microsoft.com/v1.0/users/{$from}/sendMail";
        try {
            $response = $this->httpClient->request('POST', $endpoint, [
                'headers' => [
                    'Authorization' => "Bearer {$accessToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => ['message' => $message],
            ]);

            if ($response->getStatusCode() === 202) {
                return new Response('<h1>Correo enviado correctamente</h1>', 200, ['Content-Type' => 'text/html']);
            } else {
                return new JsonResponse(['error' => 'Failed to send email: ' . $response->getContent(false)], $response->getStatusCode());
            }
        } catch (\Exception $e) {
            error_log('MailController error: ' . $e->getMessage());
            return new JsonResponse(['error' => 'Failed to send email: ' . $e->getMessage()], 500);
        }
    }
}
