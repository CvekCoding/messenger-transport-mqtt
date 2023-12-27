<?php

namespace VSPoint\Messenger\Transport\Mqtt;

use Mosquitto\Client;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\ReceiverInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\Serializer\SerializerInterface;

class MqttTransport implements TransportInterface
{
    private Client $client;
    private array $credentials;
    private bool $connected;
    private string $caCert;
    private bool $shouldStop;
    private array $topics;

    public function __construct(string $caCert, array $credentials, array $topics)
    {
        $this->credentials = $credentials;
        $this->caCert = $caCert;
        $this->connected = false;
        $this->topics = $topics;
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
        foreach ($this->connection->getQueueNames() as $queueName) {
            yield from $this->getEnvelope($queueName);
        }
        $this->client->onMessage(function($message) use ($handler) {
            yield from new MqttMessage($message->topic,$message->qos,$message->payload,$message->mid);
        });
        $this->subscribe();
        $this->client->loopForever();
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
     * @return Client
     */
    private function createClient(string $username, string $password): Client
    {
        $client = new Client($this->credentials['client_id'],false);
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

    private function subscribe(string $username, string $password) {

        $this->connect($username, $password);
        foreach ($this->topics as $topic) {
            $this->client->subscribe($topic, 0);
        }
    }
}
