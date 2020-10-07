<?php

namespace VSPoint\Messenger\Transport\Mqtt;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Serializer\SerializerInterface;

class MqttTransport implements TransportInterface
{
    private \Mosquitto\Client $client;
    private array $credentials;
    private bool $connected;
    private string $caCert;

    public function __construct(string $caCert, array $credentials)
    {
        $this->credentials = $credentials;
        $this->caCert = $caCert;
        $this->connected = false;
    }

    public function send(Envelope $envelope): Envelope
    {
        $message = $envelope->getMessage();
        if($message instanceof MqttMessageInterface) {
            $this->connect($message->getUsername(), $message->getPassword());
            $this->client->publish($message->getTopic(), $message->getBody(), $message->getQos());
            $this->client->loopForever();
        }

        return $envelope;
    }

    public function get(): iterable
    {
        throw new \InvalidArgumentException('Not implemented. Please use triggers instead');
    }

    public function ack(Envelope $envelope): void
    {
    }

    public function reject(Envelope $envelope): void
    {
    }

    public function stop(): void
    {
        $this->client->exitLoop();
        if(isset($this->client)) {
            $this->client->disconnect();
            unset($this->client);
        }
    }

    /**
     * Creates new instance of a MQTT client
     *
     * @return \Mosquitto\Client
     */
    private function createClient(string $username, string $password): \Mosquitto\Client
    {
        $client = new \Mosquitto\Client($this->credentials['client_id'],false);
        $client->setTlsCertificates($this->caCert);
        $client->setCredentials($username, $password);

        $client->onDisconnect(function(){
            $this->connected = false;
        });

        // We need to close connection to complete publishing
        $client->onPublish(function(){
            $this->stop();
            $this->connected = false;
        });

        return $client;
    }

    private function connect(string $username, string $password): void
    {
        if(!isset($this->client)) {
            $this->client = $this->createClient($username, $password);
        }

        if(false === $this->connected) {
            $this->client->connect($this->credentials['host'],$this->credentials['port']);
            $this->connected = true;
        }
    }
}
