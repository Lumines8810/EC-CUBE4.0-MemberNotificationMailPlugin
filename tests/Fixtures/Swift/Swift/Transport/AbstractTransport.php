<?php

abstract class Swift_Transport_AbstractTransport implements Swift_Transport
{
    public function isStarted(): bool
    {
        return true;
    }

    public function start(): void
    {
    }

    public function stop(): void
    {
    }

    public function registerPlugin(Swift_Events_EventListener $plugin): void
    {
    }
}
