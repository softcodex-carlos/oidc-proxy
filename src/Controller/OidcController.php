<?php

namespace App\Controller;

use OidcProxy\Config;
use OidcProxy\OidcProxy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class OidcController extends AbstractController
{
    #[Route('/oidc/sso', name: 'oidc_sso', methods: ['POST'])]
    public function sso(Request $request): Response
    {
        $clientId = $_ENV['CLIENT_ID'];
        $clientSecret = $_ENV['CLIENT_SECRET'];
        $tenantId = $request->request->get('tenant_id');
        $origin = $request->request->get('origin');
        $authorizationBaseUrl = $request->request->get('authorization_base_url');
        $allowedAuthUrlPrefix = $_ENV['OIDC_ALLOWED_AUTH_URL_PREFIX'];

        if (!$tenantId || !$origin || !$authorizationBaseUrl) {
            return new Response('Missing parameters', 400);
        }

        if (!$clientId || !$clientSecret) {
            return new Response('Invalid configuration in proxy', 500);
        }

        if (!filter_var($origin, FILTER_VALIDATE_URL)) {
            return new Response('Invalid origin URL', 400);
        }

        $authUrlWithClientId = $authorizationBaseUrl . '&client_id=' . urlencode($clientId);

        if (!str_starts_with($authorizationBaseUrl, $allowedAuthUrlPrefix)) {
            return new Response('Invalid authorization URL', 400);
        }

        $request->getSession()->set('oauth2_origin', $origin);
        $request->getSession()->set('client_config', [
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'tenant_id' => $tenantId,
            'allowed_email_domains' => $request->request->get('allowed_email_domains', ''),
            'excluded_email_domains' => $request->request->get('excluded_email_domains', ''),
        ]);

        return new RedirectResponse($authUrlWithClientId);
    }

    #[Route('/oidc/callback', name: 'oidc_callback', methods: ['GET'])]
    public function callback(Request $request): Response
    {
        $state = $request->query->get('state');
        $code = $request->query->get('code');
        $error = $request->query->get('error');
        $storedState = $request->getSession()->get('oauth2_state');
        $origin = $request->getSession()->get('oauth2_origin');
        $clientConfig = $request->getSession()->get('client_config');

        if ($error) {
            return new Response('OIDC error: ' . $error, 400);
        }

        if (!$state || !$code || !$origin || !$clientConfig || $state !== $storedState) {
            return new Response('Invalid or missing session data/state', 400);
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

            $email = $result['userData']['mail'] ?? $result['userData']['userPrincipalName'] ?? '';
            $displayName = $result['userData']['displayName'] ?? ucfirst(strtolower(explode('@', $email)[0]));

            if (!$email) {
                return new Response('Missing email in user data', 400);
            }

            $emailDomain = strtolower(substr(strrchr($email, '@'), 1));

            $excludedDomains = array_filter(array_map('trim', explode(',', $clientConfig['excluded_email_domains'] ?? '')));
            if (!empty($excludedDomains) && in_array($emailDomain, $excludedDomains)) {
                return new Response('Email domain not allowed', 403);
            }

            $allowedDomains = array_filter(array_map('trim', explode(',', $clientConfig['allowed_email_domains'] ?? '')));
            if (!empty($allowedDomains) && !in_array($emailDomain, $allowedDomains)) {
                return new Response('Email domain not allowed', 403);
            }

            $query = http_build_query([
                'accessToken' => $result['accessToken'],
                'email' => $email,
                'displayName' => $displayName,
            ]);

            $request->getSession()->remove('oauth2_state');
            $request->getSession()->remove('oauth2_origin');
            $request->getSession()->remove('client_config');

            return new RedirectResponse($origin . '?' . $query);
        } catch (\Throwable $e) {
            return new Response('OIDC Error: ' . $e->getMessage(), 500);
        }
    }
}