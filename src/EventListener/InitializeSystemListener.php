<?php
namespace  LeadingSystems\CajaxBundle\EventListener;

use Contao\CoreBundle\Framework\ContaoFramework;
use LeadingSystems\Cajax\ls_cajax_mainController;

class InitializeSystemListener
{

    public function __construct()
    {
    }

    public function __invoke(): void
    {
        ls_cajax_mainController::getInstance()->receiveRequestData();
    }
}