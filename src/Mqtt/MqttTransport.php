<?php

namespace VSPoint\Messenger\Transport\Mqtt;

use Mosquitto\Client;
use Mosquitto\Exception;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\TransportInterface;

class MqttTransport implements TransportInterface
{
    private array $credentials;
    private string $caCert;
    private array $topics;
    private ?Client $client = null;
    private bool $connected = false;
    private ?MqttMessage $message = null;

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
            try {
                $this->client->loop(); // to check the client connection
            } catch (Exception $e) { // if client was unexpectedly disconnected, reconnect it
                $this->connect($message->getUsername(), $message->getPassword());
            }

            $this->client->publish($message->getTopic(), $message->getBody(), $message->getQos());
            $this->client->loopForever();
        }

        return $envelope;
    }

    public function get(): iterable
    {
        $this->subscribe($this->credentials['login'], $this->credentials['password']);
        $this->client->loopForever();

        if (isset($this->message)) {
            $envelope = new Envelope($this->message);
            $this->message = null;

            return [$envelope];
        }

        return [];
    }

    public function ack(Envelope $envelope): void
    {
    }

    public function reject(Envelope $envelope): void
    {
    }

    /**
     * Creates new instance of a MQTT client
     *
     * @return \Mosquitto\Client
     */
    private function createClient(string $username, string $password): \Mosquitto\Client
    {
        $client = new Client($this->credentials['client_id'],false);
        $client->setTlsCertificates($this->caCert);
        $client->setCredentials($username, $password);

        $client->onDisconnect(function() {
            $this->connected = false;
        });

        $client->onConnect(function() {
            $this->connected = true;
        });

        // We need to close connection to complete publishing
        $client->onPublish(function() use ($client) {
            $client->exitLoop();
        });

        $client->onMessage(function($message) use ($client) {
            $client->exitLoop();
            $this->message = new MqttMessage($message->topic,$message->qos,$message->payload,$message->mid);
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
        }
    }

    private function subscribe(string $username, string $password): void
    {
        if ($this->connected) {
            return;
        }

        $this->connect($username, $password);
        foreach ($this->topics as $topic) {
            $this->client->subscribe($topic, 1);
        }
    }
}
