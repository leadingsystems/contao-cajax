<?php

namespace LeadingSystems\Cajax;

//$GLOBALS['TL_HOOKS']['initializeSystem'][] = array('LeadingSystems\Cajax\ls_cajax_mainController', 'receiveRequestData');
$GLOBALS['TL_HOOKS']['isVisibleElement'][] = array('LeadingSystems\Cajax\ls_cajax_mainController', 'filterElementsToRender');
$GLOBALS['TL_HOOKS']['modifyFrontendPage'][] = array('LeadingSystems\Cajax\ls_cajax_mainController', 'modifyOutput');
$GLOBALS['TL_HOOKS']['outputBackendTemplate'][] = array('LeadingSystems\Cajax\ls_cajax_mainController', 'modifyOutput');
