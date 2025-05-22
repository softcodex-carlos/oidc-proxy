<?php
namespace OidcProxy;

class Config
{
    private string $clientId;
    private string $clientSecret;
    private string $tenantId;
    private string $redirectUri;
    private array $scopes;
    private string $urlAuthorize;
    private string $urlAccessToken;
    private string $urlResourceOwnerDetails;

    public function __construct(
        string $clientId,
        string $clientSecret,
        string $redirectUri,
        string $tenantId = '13742973-0efb-4619-96a6-49f1797957e3',
        array $scopes = ['openid', 'profile', 'email', 'https://graph.microsoft.com/User.Read']
    ) {
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->tenantId = $tenantId;
        $this->redirectUri = $redirectUri;
        $this->scopes = $scopes;
        $this->urlAuthorize = sprintf('https://login.microsoftonline.com/%s/oauth2/v2.0/authorize', $tenantId);
        $this->urlAccessToken = sprintf('https://login.microsoftonline.com/%s/oauth2/v2.0/token', $tenantId);
        $this->urlResourceOwnerDetails = 'https://graph.microsoft.com/v1.0/me';
    }

    public function getClientId(): string { return $this->clientId; }
    public function getClientSecret(): string { return $this->clientSecret; }
    public function getTenantId(): string { return $this->tenantId; }
    public function getRedirectUri(): string { return $this->redirectUri; }
    public function getScopes(): array { return $this->scopes; }
    public function getUrlAuthorize(): string { return $this->urlAuthorize; }
    public function getUrlAccessToken(): string { return $this->urlAccessToken; }
    public function getUrlResourceOwnerDetails(): string { return $this->urlResourceOwnerDetails; }
}