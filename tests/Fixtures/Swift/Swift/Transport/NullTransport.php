<?php

class Swift_Transport_NullTransport extends Swift_Transport_AbstractTransport
{
    public function send(Swift_Message $message, &$failedRecipients = null): int
    {
        return count($message->getTo());
    }
}
