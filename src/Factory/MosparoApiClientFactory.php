<?php

namespace MosparoRpg\Factory;

use Mosparo\ApiClient\Client;

class MosparoApiClientFactory
{
    public function getClient(string $mosparoHost, string $mosparoPublicKey, string $mosparoPrivateKey, array $options): Client
    {
        return new Client($mosparoHost, $mosparoPublicKey, $mosparoPrivateKey, $options);
    }
}