#!/bin/bash

set -e

BASE_URL="https://localhost:2443/api/crypto/v1"
TEXT_TO_TEST="Hello World!"

CURL_OPTS="-k -s -H 'Content-Type: application/json'"

echo "🔐 Encrypting..."
ENCRYPT_RESPONSE=$(curl $CURL_OPTS -X POST "$BASE_URL/encrypt" -d "{\"text\": \"$TEXT_TO_TEST\"}")
CIPHERTEXT=$(echo "$ENCRYPT_RESPONSE" | jq -r .ciphertext)
echo "Ciphertext: $CIPHERTEXT"

echo "🔓 Decrypting..."
DECRYPT_RESPONSE=$(curl $CURL_OPTS -X POST "$BASE_URL/decrypt" -d "{\"ciphertext\": \"$CIPHERTEXT\"}")
PLAINTEXT=$(echo "$DECRYPT_RESPONSE" | jq -r .plaintext)
echo "Plaintext: $PLAINTEXT"

echo "✍️  Signing..."
SIGN_RESPONSE=$(curl $CURL_OPTS -X POST "$BASE_URL/sign" -d "{\"text\": \"$TEXT_TO_TEST\"}")
SIGNATURE=$(echo "$SIGN_RESPONSE" | jq -r .signature)
echo "Signature: $SIGNATURE"

echo "✅ Verifying..."
VERIFY_RESPONSE=$(curl $CURL_OPTS -X POST "$BASE_URL/verify" -d "{\"text\": \"$TEXT_TO_TEST\", \"signature\": \"$SIGNATURE\"}")
VALID=$(echo "$VERIFY_RESPONSE" | jq -r .valid)
echo "Valid: $VALID"

echo "🎉 Test successful complete!"