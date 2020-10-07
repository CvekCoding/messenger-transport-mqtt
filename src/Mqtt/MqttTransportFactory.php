<?php

namespace VSPoint\Messenger\Transport\Mqtt;

use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;

class MqttTransportFactory implements TransportFactoryInterface
{
    private ?string $clientId;
    private string $caCert;

    public function __construct(string $caCert, ?string $clientId = null)
    {
        $this->clientId = $clientId;
        $this->caCert = $caCert;
    }

    public function createTransport(string $dsn, array $options): TransportInterface
    {
        if (false === $parsedUrl = parse_url($dsn)) {
            throw new \InvalidArgumentException(sprintf('The given AMQP DSN "%s" is invalid.', $dsn));
        }

        $pathParts = isset($parsedUrl['path']) ? explode('/', trim($parsedUrl['path'], '/')) : [];

        $credentials = \array_replace_recursive([
            'host'      => $parsedUrl['host'] ?? 'localhost',
            'port'      => $parsedUrl['port'] ?? 1883,
            'client_id' => $this->clientId ?? (string) getmypid(),
            'vhost'     => isset($pathParts[0]) ? urldecode($pathParts[0]) : '/',
        ], $options);

        if (isset($parsedUrl['user'])) {
            $credentials['login'] = $parsedUrl['user'];
        }

        if (isset($parsedUrl['pass'])) {
            $credentials['password'] = $parsedUrl['pass'];
        }

        return new MqttTransport($this->caCert, $credentials);
    }

    public function supports(string $dsn, array $options): bool
    {
        return 0 === \strpos($dsn, 'mqtt://');
    }
}
