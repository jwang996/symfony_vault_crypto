# Symfony Vault Crypto

This project is a secure Symfony-based REST API for cryptographic operations using **HashiCorp Vault**. It leverages the **Transit Engine** for encryption, decryption, signing, and verification, along with a custom salt for enhanced security.

## Why This Project?

I want to provide a reference solution for how **Symfony** can cooperate with **HashiCorp Vault** for secure cryptographic operations.  

During my DevOps work setting up Vault in production environments, I gained hands-on experience with its architecture and capabilities.  

I also realized that the **Transit Secrets Engine** is a powerful way to offload encryption, decryption, and digital signing to Vault without managing keys manually in the application.

As a PHP developer who enjoys working with Symfony, I wanted to provide an example of how to use **Vault’s Transit Secrets Engine** within a Symfony application.

To demonstrate this integration, I built a simple and clean example using Symfony’s service architecture.  
This project showcases how to encrypt, decrypt, sign, and verify data using Vault’s APIs securely from a Symfony app.

In today's world, **data encryption is essential** not just for compliance, but also for reducing the blast radius in case of a breach. 

While using Vault as an external cryptographic service introduces **performance tradeoffs** due to network I/O and request latency, it’s a secure and centralized solution—especially valuable for high-trust environments where key management and auditability are critical.

## Repository Structure
```
.
├── docker
│   ├── nginx
│   │   ├── default.conf
│   │   └── certs/
│   └── vault
│       └── entrypoint.sh
├── docker-compose.yml
├── symfony
│   ├── src/
│   ├── config/
│   └── ...
├── test_vault_endpoints.sh
└── README.md                   ← (You are here)
```

## Features

* Encrypt and decrypt sensitive data using Vault's Transit Engine
* Sign and verify data with `ed25519` key pair
* Use application-level salt stored in Vault for secure signing
* Vault initialized automatically with keys and secrets via Docker entrypoint
* Rate-limiting protection against brute-force attacks on signature verification
* HTTPS-secured API access via Nginx

---

## API Endpoints

All endpoints are prefixed with: `https://localhost:2443/api/crypto/v1`

| Method | Path     | Description                     |
| ------ | -------- | ------------------------------- |
| POST   | /encrypt | Encrypt plaintext               |
| POST   | /decrypt | Decrypt ciphertext              |
| POST   | /sign    | Sign plaintext + salt           |
| POST   | /verify  | Verify signed message with salt |

---

## Before you start

Please create self-signed certs for nginx:

```
mkdir -p docker/nginx/certs

openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
-keyout docker/nginx/certs/server.key \
-out docker/nginx/certs/server.crt \
-subj "/C=DE/ST=NRW/L=Duesseldorf/O=Dev/CN=localhost"`
```

and make sure the entrypoint.sh file has the right permission:

`chmod +x ./docker/vault/entrypoint.sh`

## Vault Entrypoint

File: `docker/vault/entrypoint.sh`

This script runs Vault in dev mode and performs:

1. Starts Vault server on `0.0.0.0:8200`
2. Logs in with root token
3. Enables the `transit` and `kv` secret engines
4. Creates:

    * Data encryption key (DEK)
    * Signing key (ed25519)
    * Application salt as KV secret

Example excerpt:

```sh
vault secrets enable -path=transit transit
vault write -f "transit/keys/${DEK_KEY}"
vault write "transit/keys/${SIGN_KEY}" type=ed25519
vault secrets enable -path=secret kv
vault kv put "$KV_PATH" app_salt="My$ecretS@lt123"
```

---

## Docker Setup

```yaml
services:
  php:
    environment:
      VAULT_ADDR: "http://vault:8200"
      VAULT_TOKEN: "root"
      VAULT_DEK_KEY: "symfony-dek-key"
      VAULT_SIGN_KEY: "symfony-sign-key"
      VAULT_KV_PATH: "secret/data/symfony-crypto-salt"
  vault:
    image: hashicorp/vault:1.14.2
    entrypoint: ["/vault/entrypoint.sh"]
```

---

## VaultService

File: `src/Service/VaultService.php`

The service handles all Vault interactions.

* `encrypt()` → uses Transit Engine
* `decrypt()` → uses Transit Engine
* `sign()` → appends salt from KV before signing
* `verify()` → appends salt to input before verifying

```php
$salted = $input . $this->salt;
'input' => base64_encode($salted)
```

---

## Rate Limiting (Protection)

To prevent **brute-force attacks** on `/verify`, the `rate_limiter` component is configured:

**`config/packages/rate_limiter.yaml`**

```yaml
rate_limiter:
  verify_signature:
    policy: 'fixed_window'
    limit: 5
    interval: '1 minute'
```

**In controller:**

```php
try {
    $valid = $this->vault->verify($text, $signature);
    return $this->json(['valid' => $valid]);
} catch (ClientExceptionInterface $e) {
    return $this->json(['error' => 'Invalid signature or malformed request.'], 400);
}
```

---

## Testing the API

Use the included `test_vault_endpoints.sh` script to test all endpoints:

```bash
chmod +x test_vault_endpoints.sh
./test_vault_endpoints.sh
```

---

## Security Design Notes

* Vault never stores data, only provides cryptographic operations.
* Salt is stored securely in Vault's KV engine.
* Signature verification uses salted input to prevent rainbow table attacks.
* Rate limiter defends against repeated brute-force signature tests.
* No plaintext data stored in DB; encryption must be handled before persistence.

---

## Final words

This VaultService is just a minimal example and should be adapted depending on the production Vault configuration. For example, in real deployments:

### Per-user Vault token setup:

You can pass the Vault token dynamically per request:

```
$vaultToken = $this->getTokenForUser($userId); // Token mapped to Vault policy for user
$vaultService = new VaultService($http, $url, $vaultToken, ...);
```

### EventListener:

The VaultService can also be injected into:

`Doctrine Event Listeners` to automatically encrypt/decrypt sensitive fields

`Security/EventSubscriber` to help sign or validate JWT claims

## Further Reading

To dive deeper into the technologies and concepts used in this project:

- [Vault Transit Secrets Engine – Official Docs](https://developer.hashicorp.com/vault/docs/secrets/transit)
- [Vault KV Secrets Engine – Official Docs](https://developer.hashicorp.com/vault/docs/secrets/kv)
- [Symfony HTTP Client](https://symfony.com/doc/current/http_client.html)
- [Symfony RateLimiter Component](https://symfony.com/doc/current/rate_limiter.html)
- [HashiCorp Vault Production Hardening Guide](https://developer.hashicorp.com/vault/docs/best-practices/production-hardening)
- [How Vault Handles Secrets Engines and Paths](https://developer.hashicorp.com/vault/docs/secrets#secrets-engines)

Happy encrypting your data! 🔐

