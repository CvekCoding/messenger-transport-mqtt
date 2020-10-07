<?php
namespace VSPoint\Messenger\Transport\Mqtt;

interface MqttMessageInterface
{
    public function getUsername() : string;
    public function getPassword() : string;
    public function getTopic() : string;
    public function getQos() : int;
    public function getBody() : string;
}