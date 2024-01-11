<?php

namespace LeadingSystems\CajaxBundle\EventListener;

use Contao\CoreBundle\Framework\ContaoFramework;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use LeadingSystems\Cajax\ls_cajax_mainController;


class KernelListener
{
    private $framework;

    public function __construct(ContaoFramework $framework)
    {
        $this->framework = $framework;
    }

    public function onKernelControllerArguments(ControllerArgumentsEvent $event)
    {

        //contao framework is not initialized for images etc. so we can just return and dont get any errors
        if (!$this->framework->isInitialized()) {
            return;
        }

        ls_cajax_mainController::getInstance()->receiveRequestData();
    }
}