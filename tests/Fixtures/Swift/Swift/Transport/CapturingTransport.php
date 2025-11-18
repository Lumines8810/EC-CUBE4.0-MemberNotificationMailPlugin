<?php

class Swift_Transport_CapturingTransport extends Swift_Transport_AbstractTransport
{
    /** @var array<int, Swift_Mime_SimpleMessage> */
    private $messages = [];

    public function send(Swift_Mime_SimpleMessage $message, &$failedRecipients = null): int
    {
        $this->messages[] = $message;

        return count($message->getTo());
    }

    /**
     * @return array<int, Swift_Mime_SimpleMessage>
     */
    public function messages(): array
    {
        return $this->messages;
    }

    public function ping(): bool
    {
        return true;
    }
}
