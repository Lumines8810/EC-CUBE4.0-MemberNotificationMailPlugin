<?php

class Swift_Mailer
{
    /** @var Swift_Transport */
    private $transport;

    /** @var array<int, Swift_Message> */
    private $sentMessages = [];

    public function __construct(Swift_Transport $transport)
    {
        $this->transport = $transport;
    }

    public function send(Swift_Message $message)
    {
        $this->sentMessages[] = $message;

        return $this->transport->send($message);
    }

    /**
     * @return array<int, Swift_Message>
     */
    public function getSentMessages(): array
    {
        return $this->sentMessages;
    }
}
