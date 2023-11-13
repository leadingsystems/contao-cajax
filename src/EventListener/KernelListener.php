<?php

namespace LeadingSystems\CajaxBundle\EventListener;

use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use LeadingSystems\Cajax\ls_cajax_mainController;


class KernelListener
{
    public function onKernelControllerArguments(ControllerArgumentsEvent $event)
    {
        ls_cajax_mainController::getInstance()->receiveRequestData();
    }
}