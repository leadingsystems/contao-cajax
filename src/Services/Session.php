<?php

namespace LeadingSystems\Cajax\Services;

use Contao\CoreBundle\Routing\ScopeMatcher;
use Symfony\Component\HttpFoundation\RequestStack;


class Session {
    private $requestStack;
    private $scopeMatcher;

    public function __construct(RequestStack $requestStack, ScopeMatcher $scopeMatcher) {
        $this->requestStack = $requestStack;
        $this->scopeMatcher = $scopeMatcher;
    }

    public function getSession() {
        $request = $this->requestStack->getCurrentRequest();
        $session = $request->getSession();
        return $session;
    }

}