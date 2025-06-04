<?php

namespace App\Controller;

use App\Service\VaultService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class AbstractVaultController extends AbstractController
{
    public function __construct(
        protected readonly VaultService $vault,
        protected readonly RateLimiterFactory $verifySignatureLimiter
    ) {}
}
