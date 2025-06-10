# Softcodex OIDC Proxy for Microsoft 365

The `softcodex/oidc-proxy` library handles OpenID Connect (OIDC) authentication with Microsoft 365 (M365) for a single tenant. The library simplifies authentication for any PHP project, including Symfony or plain PHP applications.

## Features
- OIDC authentication with Microsoft 365.
- Handles user data retrieval and session management.
- Configurable for local and production environments.
- Secure credential storage via environment variables.

## Requirements
- PHP 7.4 or higher
- Composer
- A registered application in the Azure Portal with OIDC configuration
- Dependency: `softcodex/oidc-proxy` (^1.0.0)

## Installation and Setup

### 1. Install the OIDC Proxy Library
Add the `softcodex/oidc-proxy` library via Composer. Since the package is hosted on GitHub, configure the repository in `composer.json`:

json
{
    "require": {
        "softcodex/oidc-proxy": "^1.0.0"
    },
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/softcodex-carlos/oidc-proxy.git"
        }
    ]
}

### 2. Save `composer.json` and Create a Version Tag:

`git clone https://github.com/softcodex-carlos/oidc-proxy.git`
`cd oidc-proxy`
`git tag 1.0.0`
`git push origin 1.0.0`

### 3. Run:

`composer update softcodex/oidc-proxy`

### 4. Configure Environment Variables
Create this credentials in your .env (you have it on.env .example):
TRUSTED_PROXIES=IPs
CLIENT_ID=
CLIENT_SECRET=

### 5. Create a Controller:
Add this two methods in your controller:

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class LoginController
{
    #[Route('/{_locale}/login/microsoft', name: 'login_microsoft', requirements: ['_locale' => 'en|it'])]
    public function loginMicrosoft(): Response
    {
        $tenantId = $_ENV['OIDC_TENANT_ID'];
        $proxyUrl = $_ENV['OIDC_APP_URL'];
        $allowedEmailDomains = $_ENV['OIDC_EMAIL_DOMAINS'];

        $origin = $this->generateUrl('login_microsoft_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->render('security/microsoft_login_redirect.html.twig', [
            'proxyUrl' => $proxyUrl,
            'tenantId' => $tenantId,
            'origin' => $origin,
            'allowedEmailDomains' => $allowedEmailDomains
        ]);
    }

    #[Route('/{_locale}/login/microsoft/callback', name: 'login_microsoft_callback', requirements: ['_locale' => 'en|it'])]
    public function loginMicrosoftCallback(Request $request, HttpClientInterface $httpClient, TokenStorageInterface $tokenStorage): Response
    {
        $code = $request->query->get('code');
        $error = $request->query->get('error');
        $email = $request->query->get('email');
        $displayName = $request->query->get('displayName');
        $accessToken = $request->query->get('accessToken');

        // Your logic here:
        // - Handle errors if $error is set
        // - If user data (email, displayName, accessToken) present: create or update user and authenticate
        // - If only code is present: perform standard OIDC token exchange (optional)
        // - Redirect user appropriately after login

        // Example placeholder response
        return new Response("Callback received. Implement login logic here.");
    }
}

### 6. Create the Twig template for Redirect
Create the file `templates/security/microsoft_login_redirect.html.twig` with:

<form id="oidcForm" method="POST" action="{{ proxyUrl }}">
    <input type="hidden" name="tenant_id" value="{{ tenantId }}">
    <input type="hidden" name="origin" value="{{ origin }}">
    <input type="hidden" name="allowed_email_domains" value="{{ allowedEmailDomains }}">
</form>
<script>
    document.getElementById('oidcForm').submit();
</script>

This form will auto-submit a POST request to the OIDC proxy with the required parameters.