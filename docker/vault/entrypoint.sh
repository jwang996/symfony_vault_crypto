#!/bin/sh
set -e

export VAULT_ADDR=http://127.0.0.1:8200

vault server -dev -dev-listen-address=0.0.0.0:8200 &
VAULT_PID=$!

echo "[*] Waiting for Vault to be ready..."
while ! vault status >/dev/null 2>&1; do
  sleep 1
done

DEK_KEY="${VAULT_DEK_KEY:-symfony-dek-key}"
SIGN_KEY="${VAULT_SIGN_KEY:-symfony-sign-key}"
KV_PATH="${VAULT_KV_PATH:-secret/symfony-crypto-salt}"

echo "[*] Logging in..."
vault login root

echo "[*] Enabling transit engine..."
vault secrets enable -path=transit transit || true

echo "[*] Creating DEK key..."
vault write -f "transit/keys/${DEK_KEY}"

echo "[*] Creating SIGN key..."
vault write "transit/keys/${SIGN_KEY}" type=ed25519

echo "[*] Enabling KV engine..."
vault secrets enable -path=secret kv || true

echo "[*] Writing app_salt..."
vault kv put "$KV_PATH" app_salt="My$ecretS@lt123"

echo "[*] Vault setup complete!"
wait $VAULT_PID