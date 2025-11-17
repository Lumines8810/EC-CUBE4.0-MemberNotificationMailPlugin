<?php

class Swift_Message
{
    private $subject;
    private $from = [];
    private $to = [];
    private $body = '';

    public function __construct(string $subject)
    {
        $this->subject = $subject;
    }

    /**
     * @param string|array $from
     */
    public function setFrom($from)
    {
        $this->from = is_array($from) ? $from : [$from];

        return $this;
    }

    public function setTo(array $to)
    {
        $this->to = $to;

        return $this;
    }

    public function setBody(string $body)
    {
        $this->body = $body;

        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getFrom(): array
    {
        return $this->from;
    }

    public function getTo(): array
    {
        return $this->to;
    }

    public function getBody(): string
    {
        return $this->body;
    }
}
