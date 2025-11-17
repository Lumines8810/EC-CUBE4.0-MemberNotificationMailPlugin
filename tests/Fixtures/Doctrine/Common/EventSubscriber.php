<?php

namespace Doctrine\Common;

interface EventSubscriber
{
    /**
     * @return array<int, string>
     */
    public function getSubscribedEvents(): array;
}
