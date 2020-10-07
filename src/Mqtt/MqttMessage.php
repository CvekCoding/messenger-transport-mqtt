<?php

namespace VSPoint\Messenger\Transport\Mqtt;

class MqttMessage implements MqttMessageInterface
{
    private int $qos;
    private string $body;
    private string $topic;
    private string $id;

    public function __construct(string $topic, int $qos, string $body, string $id)
    {
        $this->topic = $topic;
        $this->qos = $qos;
        $this->body = $body;
        $this->id = $id;
    }

    public function getTopic(): string
    {
        return $this->topic;
    }

    public function getQos(): int
    {
        return $this->qos;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUsername(): string
    {
        return '';
    }

    public function getPassword(): string
    {
        return '';
    }
}