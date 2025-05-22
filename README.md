# Symfony Project with Softcodex OIDC Proxy for Microsoft 365

The `softcodex/oidc-proxy` library to handle OpenID Connect (OIDC) authentication with Microsoft 365 (M365) for a single tenant. The library simplifies the authentication flow, allowing users to log in via Microsoft and retrieve user data for session management.

## Features
- OIDC authentication with Microsoft 365 using `softcodex/oidc-proxy`.
- Automatic user creation and session management in Symfony.
- Configurable for local and production environments.

## Requirements
- PHP 7.4 or higher
- Composer
- A registered application in the Azure Portal with OIDC configuration
- Dependencies:
  - `softcodex/oidc-proxy` (^1.0.0)
  - `league/oauth2-client` (^2.8)

## Installation

### 1. Install the OIDC Proxy Library
Add the `softcodex/oidc-proxy` library to your project via Composer. Since the package is hosted on a GitHub repository, configure the repository in `composer.json`:

```json
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
