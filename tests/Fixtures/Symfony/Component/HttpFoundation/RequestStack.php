<?php

namespace Symfony\Component\HttpFoundation;

class RequestStack
{
    /** @var Request|null */
    private $currentRequest;

    public function push(?Request $request): void
    {
        $this->currentRequest = $request;
    }

    public function getCurrentRequest(): ?Request
    {
        return $this->currentRequest;
    }
}
