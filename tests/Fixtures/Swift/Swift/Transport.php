<?php

interface Swift_Transport
{
    public function isStarted(): bool;
    public function start(): void;
    public function stop(): void;
    public function send(Swift_Message $message, &$failedRecipients = null): int;
    public function registerPlugin(Swift_Events_EventListener $plugin): void;
}
