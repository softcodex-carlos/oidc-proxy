# Softcodex OIDC Proxy for Microsoft 365

PHP library to handle OpenID Connect (OIDC) authentication with Microsoft 365 (M365) for applications using a single tenant. This library simplifies the authentication flow by providing a reusable component to initiate the OIDC flow and handle the callback from Microsoft.

## Features
- Initiates OIDC authentication with Microsoft 365.
- Handles callback processing to retrieve access tokens and user data.
- Supports dynamic redirect URIs for different environments (local, production, etc.).
- Designed for a single M365 tenant, with configurable `clientId`, `clientSecret`, and `redirectUri`.
- Compatible with Symfony or any PHP project.

## Requirements
- PHP 7.4 or higher
- [league/oauth2-client](https://github.com/thephpleague/oauth2-client) (^2.6)
- A registered application in the Azure Portal with OIDC configuration

## Installation
Add this to your composer.json:
"repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/softcodex-carlos/oidc-proxy.git"
        }
    ],
    
Install the library via Composer:

```bash
composer require softcodex/oidc-proxy
