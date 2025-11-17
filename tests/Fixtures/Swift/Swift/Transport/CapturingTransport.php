<?php

class Swift_Transport_CapturingTransport extends Swift_Transport_AbstractTransport
{
    /** @var array<int, Swift_Message> */
    private $messages = [];

    public function send(Swift_Message $message, &$failedRecipients = null): int
    {
        $this->messages[] = $message;

        return count($message->getTo());
    }

    /**
     * @return array<int, Swift_Message>
     */
    public function messages(): array
    {
        return $this->messages;
    }
}
