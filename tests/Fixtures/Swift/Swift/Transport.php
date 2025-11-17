<?php

interface Swift_Transport
{
    public function isStarted();
    public function start();
    public function stop();
    public function send(Swift_Message $message, &$failedRecipients = null);
    public function registerPlugin(Swift_Events_EventListener $plugin);
}
