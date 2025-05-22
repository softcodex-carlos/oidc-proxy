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

 ### 2. Install the OIDC Proxy Library
 Save `composer.json` and run:

 ```composer update softcodex/oidc-proxy