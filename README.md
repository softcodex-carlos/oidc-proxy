# PHP Project with Softcodex OIDC Proxy for Microsoft 365

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

git clone https://github.com/softcodex-carlos/oidc-proxy.git
cd oidc-proxy
git tag 1.0.0
git push origin 1.0.0

### 3. Run:

`composer update softcodex/oidc-proxy`

### 4. Configure Environment Variables
Create this credentials in your .env:
M365_CLIENT_ID=your-client-id
M365_CLIENT_SECRET=your-client-secret
M365_TENANT_ID=your-tenant-id
APP_URL=http://localhost:300

### 5. Create a Controller:
<?php
namespace App\Controller;

use App\Entity\TgUser;
use Doctrine\ORM\EntityManagerInterface;
use OidcProxy\Config;
use OidcProxy\OidcProxy;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class OidcController extends AbstractController
{
    private $entityManager;
    private $passwordEncoder;
    private $tokenStorage;
    private $logger;
    private $params;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordEncoder,
        TokenStorageInterface $tokenStorage,
        LoggerInterface $logger,
        ParameterBagInterface $params
    ) {
        $this->entityManager = $entityManager;
        $this->passwordEncoder = $passwordEncoder;
        $this->tokenStorage = $tokenStorage;
        $this->logger = $logger;
        $this->params = $params;
    }

    #[Route('/oidc/sso', name: 'oidc_sso')]
    public function sso(Request $request): Response
    {
        $config = new Config(
            $this->params->get('oidc_client_id'),
            $this->params->get('oidc_client_secret'),
            $this->params->get('oidc_tenant_id'),
            sprintf('%s/oidc/callback', $this->params->get('app_url'))
        );

        $proxy = new OidcProxy($config);
        $authData = $proxy->getAuthorizationUrl();

        // Store the state in the session for validation
        $request->getSession()->set('oauth2_state', $authData['state']);

        $this->logger->info('Authorization URL: ' . $authData['url']);
        return $this->redirect($authData['url']);
    }

    #[Route('/oidc/callback', name: 'oidc_callback')]
    public function callback(Request $request): Response
    {
        $this->logger->info('Callback parameters: ' . json_encode($request->query->all()));

        $state = $request->query->get('state');
        $code = $request->query->get('code');
        $storedState = $request->getSession()->get('oauth2_state');

        if (!$state || !$code) {
            $this->logger->error('Missing state or code: ' . json_encode($request->query->all()));
            return new Response('Error: Missing state or code', 400);
        }

        $config = new Config(
            $this->params->get('oidc_client_id'),
            $this->params->get('oidc_client_secret'),
            $this->params->get('oidc_tenant_id'),
            sprintf('%s/oidc/callback', $this->params->get('app_url'))
        );

        $proxy = new OidcProxy($config);

        try {
            $result = $proxy->handleCallback($code, $state, $storedState);

            $this->logger->info('OIDC Result: ' . json_encode($result));

            $accessToken = $result['accessToken'];
            $userData = $result['userData'];

            $email = $userData['mail'] ?? $userData['userPrincipalName'] ?? null;
            $username = strtolower(explode('@', $email)[0]);
            $displayName = $userData['displayName'] ?? ucfirst($username);

            if (!$email) {
                $this->logger->error('Microsoft did not return an email');
                return new Response('Error: Microsoft no devolvió un email', 400);
            }

            $user = $this->entityManager->getRepository(TgUser::class)->findOneBy(['username' => $username]);

            if (!$user) {
                $user = new TgUser();
                $user->setUsername($username);
                $user->setName($displayName);
                $user->setEmail($email);
                $user->setPassword($this->passwordEncoder->hashPassword($user, bin2hex(random_bytes(16))));
                $user->setRole('ROLE_USER');
                $this->entityManager->persist($user);
                $this->entityManager->flush();
            } else {
                $user->setName($displayName);
                $user->setEmail($email);
                $this->entityManager->flush();
            }

            $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
            $this->tokenStorage->setToken($token);
            $request->getSession()->set('_security_main', serialize($token));

            return $this->redirectToRoute('app_select_owner');
        } catch (\Exception $e) {
            $this->logger->error('Error durante el callback OIDC: ' . $e->getMessage());
            $this->logger->error('Exception details: ' . json_encode([
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace' => $e->getTraceAsString(),
            ]));
            return new Response('Error durante el callback OIDC: ' . $e->getMessage(), 500);
        }
    }
}



