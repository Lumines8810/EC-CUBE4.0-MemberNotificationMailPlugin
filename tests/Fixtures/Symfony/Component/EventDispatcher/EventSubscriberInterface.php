<?php

namespace Symfony\Component\EventDispatcher;

interface EventSubscriberInterface
{
    /**
     * @return array
     */
    public static function getSubscribedEvents();
}
