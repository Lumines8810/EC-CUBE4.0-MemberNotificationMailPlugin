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

    /**
     * @param string|array $to
     */
    public function setTo($to)
    {
        $this->to = is_array($to) ? $to : [$to];

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
