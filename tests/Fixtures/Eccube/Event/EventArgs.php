<?php

namespace Eccube\Event;

use Symfony\Component\HttpFoundation\Request;

class EventArgs
{
    /**
     * @var array<string, mixed>
     */
    private $arguments;

    /**
     * @var Request|null
     */
    private $request;

    public function __construct(array $arguments = [], ?Request $request = null)
    {
        $this->arguments = $arguments;
        $this->request = $request;
    }

    /**
     * @param string $name
     *
     * @return mixed|null
     */
    public function getArgument($name)
    {
        return $this->arguments[$name] ?? null;
    }

    /**
     * @param string $name
     * @param mixed  $value
     */
    public function setArgument($name, $value): void
    {
        $this->arguments[$name] = $value;
    }

    public function getRequest(): ?Request
    {
        return $this->request;
    }
}
