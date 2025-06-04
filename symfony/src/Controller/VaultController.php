<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;

class VaultController extends AbstractVaultController
{
    #[Route('/encrypt', name: 'encrypt', methods: ['POST'])]
    public function encrypt(Request $request): JsonResponse
    {
        $payLoad= $request->toArray()['text'] ?? null;
        if (!$payLoad) {
            return $this->json(['error' => 'Missing "text"'], 400);
        }

        try {
            return $this->json([
                'ciphertext' => $this->vault->encrypt($payLoad)
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Encryption failed: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/decrypt', name: 'decrypt', methods: ['POST'])]
    public function decrypt(Request $request): JsonResponse
    {
        $cipher = $request->toArray()['ciphertext'] ?? null;
        if (!$cipher) {
            return $this->json(['error' => 'Missing "ciphertext"'], 400);
        }

        try {
            return $this->json([
                'plaintext' => $this->vault->decrypt($cipher)
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Decryption failed: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/sign', name: 'sign', methods: ['POST'])]
    public function sign(Request $request): JsonResponse
    {
        $payLoad = $request->toArray()['text'] ?? null;
        if (!$payLoad) {
            return $this->json(['error' => 'Missing "text"'], 400);
        }

        try {
            return $this->json([
                'signature' => $this->vault->sign($payLoad)
            ]);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Signing failed: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/verify', name: 'verify', methods: ['POST'])]
    public function verify(Request $request): JsonResponse
    {
        $limiter = $this->verifySignatureLimiter->create($request->getClientIp());
        $limit = $limiter->consume();

        if (!$limit->isAccepted()) {
            $retryAfter = $limit->getRetryAfter()->getTimestamp() - time();
            throw new TooManyRequestsHttpException($retryAfter, 'Too many verification attempts. Please slow down.');
        }

        $payload = $request->toArray();
        $text = $payload['text'] ?? null;
        $signature = $payload['signature'] ?? null;

        if (!$text || !$signature) {
            return $this->json(['error' => 'Missing "text" or "signature"'], 400);
        }

        try {
            $valid = $this->vault->verify($text, $signature);
            return $this->json(['valid' => $valid]);
        } catch (ClientExceptionInterface $e) {
            return $this->json(['error' => 'Invalid signature or malformed request.'], 400);
        }
    }
}
