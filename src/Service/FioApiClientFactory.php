<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Cache\CacheInterface;

final class FioApiClientFactory
{
    public function __construct(
        private readonly CacheInterface $fioStaticData,
        private readonly CacheInterface $fioDynamicData,
        private readonly string $fioBaseUrl,
        private readonly Security $security,
    ) {
    }

    public function createForCurrentUser(): FioApiClient
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            throw new \RuntimeException('No authenticated user.');
        }

        return new FioApiClient(
            $this->fioStaticData,
            $this->fioDynamicData,
            $this->fioBaseUrl,
            $user->getFioApiKey(),
            $user->getFioUsername(),
        );
    }

    public function createWithCredentials(string $apiKey, string $username): FioApiClient
    {
        return new FioApiClient(
            $this->fioStaticData,
            $this->fioDynamicData,
            $this->fioBaseUrl,
            $apiKey,
            $username,
        );
    }
}
