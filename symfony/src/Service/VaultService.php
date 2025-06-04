<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class VaultService
{
    private string $salt;

    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly string              $vaultUrl,
        private readonly string              $vaultToken,
        private readonly string              $dekKeyName,
        private readonly string              $signKeyName,
        private readonly string $kvPath
    ) {
        $this->salt = $this->loadSalt();
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    private function loadSalt(): string
    {
        $response = $this->http->request('GET', "{$this->vaultUrl}/v1/{$this->kvPath}", [
            'headers' => ['X-Vault-Token' => $this->vaultToken],
        ]);

        return $response->toArray()['data']['data']['app_salt'] ?? '';
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function encrypt(string $plaintext): string
    {
        $response = $this->http->request('POST', "{$this->vaultUrl}/v1/transit/encrypt/{$this->dekKeyName}", [
            'headers' => ['X-Vault-Token' => $this->vaultToken],
            'json' => ['plaintext' => base64_encode($plaintext)],
        ]);

        return $response->toArray()['data']['ciphertext'];
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function decrypt(string $ciphertext): string
    {
        $response = $this->http->request('POST', "{$this->vaultUrl}/v1/transit/decrypt/{$this->dekKeyName}", [
            'headers' => ['X-Vault-Token' => $this->vaultToken],
            'json' => ['ciphertext' => $ciphertext],
        ]);

        return base64_decode($response->toArray()['data']['plaintext']);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function sign(string $input): string
    {
        $salted = $input . $this->salt;
        $response = $this->http->request('POST', "{$this->vaultUrl}/v1/transit/sign/{$this->signKeyName}", [
            'headers' => ['X-Vault-Token' => $this->vaultToken],
            'json' => ['input' => base64_encode($salted)],
        ]);

        return $response->toArray()['data']['signature'];
    }

    public function verify(string $input, string $signature): bool
    {
        $salted = $input . $this->salt;
        $response = $this->http->request('POST', "{$this->vaultUrl}/v1/transit/verify/{$this->signKeyName}", [
            'headers' => ['X-Vault-Token' => $this->vaultToken],
            'json' => [
                'input' => base64_encode($salted),
                'signature' => $signature,
            ],
        ]);

        return $response->toArray()['data']['valid'] ?? false;
    }
}
