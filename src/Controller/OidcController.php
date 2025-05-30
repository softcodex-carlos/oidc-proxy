<?php

namespace App\Controller;

use OidcProxy\Config;
use OidcProxy\OidcProxy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class OidcController extends AbstractController
{
    #[Route('/oidc/sso', name: 'oidc_sso', methods: ['POST'])]
    public function sso(Request $request): Response
    {
        $clientId = $request->request->get('client_id');
        $clientSecret = $request->request->get('client_secret');
        $tenantId = $request->request->get('tenant_id');
        $origin = $request->request->get('origin');
        $mode = $request->request->get('mode', 'proxy');

        if (!$clientId || !$clientSecret || !$tenantId || !$origin) {
            return new Response('Missing parameters', 400);
        }

        $redirectUri = $request->getSchemeAndHttpHost() . '/oidc/callback';

        $config = new Config($clientId, $clientSecret, $tenantId, $redirectUri);
        $proxy = new OidcProxy($config);
        $authData = $proxy->getAuthorizationUrl();

        $request->getSession()->set('oauth2_state', $authData['state']);
        $request->getSession()->set('oauth2_origin', $origin);
        $request->getSession()->set('client_config', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'tenant_id' => $tenantId,
        ]);

        return $this->redirect($authData['url']);
    }

    #[Route('/oidc/callback', name: 'oidc_callback')]
    public function callback(Request $request): Response
    {
        $state = $request->query->get('state');
        $code = $request->query->get('code');
        $storedState = $request->getSession()->get('oauth2_state');
        $origin = $request->getSession()->get('oauth2_origin');
        $clientConfig = $request->getSession()->get('client_config');

        if (!$state || !$code || !$origin || !$clientConfig) {
            return new Response('Missing required session data', 400);
        }

        $redirectUri = $request->getSchemeAndHttpHost() . '/oidc/callback';

        $config = new Config(
            $clientConfig['client_id'],
            $clientConfig['client_secret'],
            $clientConfig['tenant_id'],
            $redirectUri
        );

        $proxy = new OidcProxy($config);

        try {
            $result = $proxy->handleCallback($code, $state, $storedState);

            $query = http_build_query([
                'accessToken' => $result['accessToken'],
                'email' => $result['userData']['mail'] ?? $result['userData']['userPrincipalName'] ?? '',
                'displayName' => $result['userData']['displayName'] ?? '',
            ]);

            return $this->redirect($origin . '?' . $query);
        } catch (\Exception $e) {
            return new Response('OIDC Error: ' . $e->getMessage(), 500);
        }
    }
}
