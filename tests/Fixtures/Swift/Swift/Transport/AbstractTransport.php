<?php

abstract class Swift_Transport_AbstractTransport implements Swift_Transport
{
    public function isStarted()
    {
        return true;
    }

    public function start()
    {
    }

    public function stop()
    {
    }

    public function registerPlugin(Swift_Events_EventListener $plugin)
    {
    }
}
