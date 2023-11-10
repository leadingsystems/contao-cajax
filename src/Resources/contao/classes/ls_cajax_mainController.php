<?php

namespace LeadingSystems\Cajax;

use Contao\System;
use LeadingSystems\Helpers\ls_helpers_controller;
use Symfony\Component\HttpFoundation\Request;

class ls_cajax_mainController {
    protected static $objInstance;

    private function __clone() {}

    public static function getInstance() {
        if (!is_object(self::$objInstance))	{
            self::$objInstance = new self();
        }
        return self::$objInstance;
    }

    /*
     * The ls_cajax requestData holds information about what we need to get
     * as a response. This information can be given as POST or GET data and
     * is stored in the session, so that it's still available after a reload
     * or redirect that might occur.
     */
    public function receiveRequestData() {
        /*
         * If cajaxRequestData is sent as a post parameter with a submitted form, contao saves the parameter in the
         * session (FORM_DATA) which later can lead to a falsely detected cajax request. Therefore, we remove the
         * cajaxRequestData parameter form $_SESSION['FORM_DATA']
         */
        if (isset($_SESSION['FORM_DATA']['cajaxRequestData'])) {
            unset($_SESSION['FORM_DATA']['cajaxRequestData']);
        }

		/*
		 * Make sure that a non-ajax request without cajaxRequestData will never
		 * be treated as a cajax request even if there is leftover cajax data in
		 * the session, which can happen if a previous cajax request could not be
		 * finished due to an error.
		 */

        $session = \System::getContainer()->get('cajax.session')->getSession();
        $session_lsCajax =  $session->get('lsCajax', []);

		if (!\Environment::get('isAjaxRequest') && isset($session_lsCajax)) {
            $session->set('lsCajax', false);
		}

		if (\Input::get('cajaxRequestData') || \Input::post('cajaxRequestData')) {
            $session_lsCajax['requestData'] = (\Input::get('cajaxRequestData') ?: \Input::post('cajaxRequestData')) ?: null;

			if (!is_array($session_lsCajax['requestData'])) {
				/*
				 * Check whether the requestData is JSON and can be decoded as such
				 */
				$arr_tmp_jsonDecodedRequestData = json_decode(html_entity_decode($session_lsCajax['requestData']), true);
				if (is_array($arr_tmp_jsonDecodedRequestData)) {
                    $session_lsCajax['requestData'] = $arr_tmp_jsonDecodedRequestData;
				}
			}
		}

        if (\Input::get('cajaxRequestData')) {
            \Environment::set('request', ls_helpers_controller::getUrl(false, array('cajaxRequestData')));
        }

        if (
                \Environment::get('isAjaxRequest')
            &&	(
                    \Input::get('cajaxRequestData')
                ||	\Input::post('cajaxRequestData')
                )
        ) {
            $this->removeCacheBustingParameter();
        }

		if (($session_lsCajax['requestData'] ?? null) !== null) {
			/*
			 * ->
			 * Contao deals with ajax requests in a way that does not play nicely
			 * with the ls_cajax approach. For example, Contao does not follow redirects
			 * or even reloads directly if it detects an ajax request. Instead it
			 * sends the "X-Ajax-Location" header which should then be handled on
			 * the client side. Mootao redirects the entire page if it receives
			 * the X-Ajax-Location header but we don't want that to happen in case
			 * of a ls_cajax request. We simply want the ajax request to be redirected
			 * and then receive the output of the final page.
			 *
			 * We solve this problem by not identifying an ls_cajax request
			 * as an ajax request
			 */
			\Environment::set('isAjaxRequest', false);
			/*
			 * <-
			 */

			$this->handleRenderingFilterInput();
		}
        $session->set('lsCajax', $session_lsCajax);
		\Input::setGet('cajaxRequestData', null);
	}

	/*
	 * Make sure that the given rendering filter input makes sense.
	 *
	 * The js object construction for the cajax call must look like this:
	 *
	 * 	'cajaxRequestData': {
			'requestedElementID': 'top',
			'renderingFilter': {
				'articles': {
					'filterMode': 'whitelist', // 'whitelist', 'blacklist', 'all', 'none'
					'pattern': [ // Only required for whitelists and blacklists. Takes an array of regex pattern strings in php regex flavor.
						'test'
					]
				},
				'contentElements': {
					'filterMode': 'whitelist', // 'whitelist', 'blacklist', 'all', 'none'
					'pattern': [ // Only required for whitelists and blacklists. Takes an array of regex pattern strings in php regex flavor.
						'test'
					]
				},
				'modules': {
					'filterMode': 'whitelist', // 'whitelist', 'blacklist', 'all', 'none'
					'pattern': [ // Only required for whitelists and blacklists. Takes an array of regex pattern strings in php regex flavor.
						'test'
					]
				}
			}
		}
	 */
	protected function handleRenderingFilterInput() {
        $session = \System::getContainer()->get('cajax.session')->getSession();
        $session_lsCajax =  $session->get('lsCajax', []);

        $session_lsCajax['bln_useRenderingFilter'] = array(
			'any' => false,
			'articles' => false,
			'contentElements' => false,
			'modules' => false
		);
        $session->set('lsCajax', $session_lsCajax);

		if (!isset($session_lsCajax['requestData']['renderingFilter'])) {
			return;
		}

		if (!is_array($session_lsCajax['requestData']['renderingFilter'])) {
			unset($session_lsCajax['requestData']['renderingFilter']);
            $session->set('lsCajax', $session_lsCajax);
			return;
		}
		
		$this->handleRenderingFilterElementInputByType('articles');
		$this->handleRenderingFilterElementInputByType('contentElements');
		$this->handleRenderingFilterElementInputByType('modules');


        $session_lsCajax['bln_useRenderingFilter']['any']
			=
                $session_lsCajax['bln_useRenderingFilter']['articles']
			||	$session_lsCajax['bln_useRenderingFilter']['contentElements']
			||	$session_lsCajax['bln_useRenderingFilter']['modules'];

        $session->set('lsCajax', $session_lsCajax);
	}

	protected function handleRenderingFilterElementInputByType($str_elementType = 'articles') {
        $session = \System::getContainer()->get('cajax.session')->getSession();
        $session_lsCajax =  $session->get('lsCajax', []);

		/*
		 * Make sure that we have an allowed filterMode
		 */
		$arr_allowedFilterModes = array('whitelist', 'blacklist', 'all', 'none');
		if (!in_array($session_lsCajax['requestData']['renderingFilter'][$str_elementType]['filterMode'], $arr_allowedFilterModes)) {
            $session_lsCajax['requestData']['renderingFilter'][$str_elementType]['filterMode'] = 'none';
		}

		/*
		 * If we have a pattern, we make sure that it contains a filled array
		 * and if it doesn't, we dismiss the pattern
		 */
		if (isset($session_lsCajax['requestData']['renderingFilter'][$str_elementType]['pattern'])) {
			/*
			 * If it's not an array, we make one
			 */
			if (!is_array($session_lsCajax['requestData']['renderingFilter'][$str_elementType]['pattern'])) {
                $session_lsCajax['requestData']['renderingFilter'][$str_elementType]['pattern'] = array($session_lsCajax['requestData']['renderingFilter'][$str_elementType]['pattern']);
			}

			/*
			 * We unset all empty array elements...
			 */
			foreach ($session_lsCajax['requestData']['renderingFilter'][$str_elementType]['pattern'] as $k => $v) {
				if (!$v) {
					unset($session_lsCajax['requestData']['renderingFilter'][$str_elementType]['pattern'][$k]);
				}
			}
			/*
			 * ... and if none are left, we dismiss the pattern
			 */
			if (!count($session_lsCajax['requestData']['renderingFilter'][$str_elementType]['pattern'])) {
				unset($session_lsCajax['requestData']['renderingFilter'][$str_elementType]['pattern']);
			}
		}

		/*
		 * If we don't have a pattern but the filterMode is neither "all" nor "none",
		 * we dismiss this filter
		 */
		if (
				!isset($session_lsCajax['requestData']['renderingFilter'][$str_elementType]['pattern'])
			&&	$session_lsCajax['requestData']['renderingFilter'][$str_elementType]['filterMode'] != 'all'
			&&	$session_lsCajax['requestData']['renderingFilter'][$str_elementType]['filterMode'] != 'none'
		) {
			unset($session_lsCajax['requestData']['renderingFilter'][$str_elementType]);
            $session->set('lsCajax', $session_lsCajax);
			return;
		}

		/*
		 * If the filter mode is either 'all' or 'none', we dismiss the pattern
		 * because it won't be used anyway
		 */
		if (
                $session_lsCajax['requestData']['renderingFilter'][$str_elementType]['filterMode'] == 'all'
			||	$session_lsCajax['requestData']['renderingFilter'][$str_elementType]['filterMode'] == 'none'
		) {
			unset($session_lsCajax['requestData']['renderingFilter'][$str_elementType]['pattern']);
		}

        $session_lsCajax['bln_useRenderingFilter'][$str_elementType] = true;
        $session->set('lsCajax', $session_lsCajax);
	}

	/*
	 * This function prevents all elements, that should be filtered out in the
	 * cajax context, from being rendered
	 */
	public function filterElementsToRender($obj_element, $bln_isVisible) {
		/*
		 * Don't do anything for elements that already shouldn't be visible
		 * irrespective of the cajax rendering filter
		 */
		if (!$bln_isVisible) {
			return $bln_isVisible;
		}

		if ($obj_element instanceof \ContentModel) {
			$str_elementType = 'contentElements';
		} else if ($obj_element instanceof \ModuleModel) {
			$str_elementType = 'modules';
		} else {
			$str_elementType = 'articles';
		}

		/*
		 * Don't do anything if no relevant cajax rendering filter is set
		 */
        $session = \System::getContainer()->get('cajax.session')->getSession();
        $session_lsCajax =  $session->get('lsCajax', []);

		if (
				!($session_lsCajax['bln_useRenderingFilter']['any'] ?? null)
			||	!($session_lsCajax['bln_useRenderingFilter'][$str_elementType] ?? null)
		) {
			return $bln_isVisible;
		}

        $bln_isVisible = $this->determineRequiredVisibility($str_elementType, $bln_isVisible, $obj_element->cajaxIdentifierString);

        return $bln_isVisible;
    }

	protected function determineRequiredVisibility($str_elementType, $bln_isVisible, $str_cajaxIdentification) {
		if (!$str_elementType) {
			return $bln_isVisible;
		}

        $session = \System::getContainer()->get('cajax.session')->getSession();
        $session_lsCajax =  $session->get('lsCajax', []);

		/*
		 * With filterMode 'all' and 'none', we simply set the visibility flag
		 * to true or false for all elements
		 */
		if ($session_lsCajax['requestData']['renderingFilter'][$str_elementType]['filterMode'] == 'all') {
			/*
			 * We want to filter out all elements
			 */
			return false;
		} else if ($session_lsCajax['requestData']['renderingFilter'][$str_elementType]['filterMode'] == 'none') {
			/*
			 * We want to filter out none of the elements, so we return
			 * the visibility status as it already is.
			 */
			return $bln_isVisible;
		}

		/*
		 * If we have a whitelist, we set the visibility of an element to false
		 * by default. In case of a blacklist, an element is visible by default,
		 * which is already the case.
		 */
		if ($session_lsCajax['requestData']['renderingFilter'][$str_elementType]['filterMode'] == 'whitelist') {
			$bln_isVisible = false;
		}

		/*
		 * If one of the given patterns matches the ajax identification string
		 * we simply switch the visibility flag and return it
		 */
		foreach ($session_lsCajax['requestData']['renderingFilter'][$str_elementType]['pattern'] as $str_pattern) {
			if (preg_match('/'.preg_quote($str_pattern, '/').'/', $str_cajaxIdentification)) {
				return !$bln_isVisible;
			}
		}

		return $bln_isVisible;
	}
	
	/*
	 * This function makes sure that only the contents of the html element with
	 * the requested id will be sent to the client
	 */
	public function modifyOutput($str_content, $str_template) {
        $session = \System::getContainer()->get('cajax.session')->getSession();
        $session_lsCajax =  $session->get('lsCajax', []);

	    if (!is_array($session_lsCajax ?? null)) {
	        return $str_content;
        }

		/*
		 * Because the first thing we want to do in this function is to unset
		 * the cajax specific session data but we need the data later in this
		 * function, we temporarily store it in a variable.
		 */
		$tmp_ls_cajax = $session_lsCajax;

		/*
		 * unset all cajax specific data if an output is generated because
		 * in this case the cajax call is definitely finished
		 */
		unset($session_lsCajax['requestData']);
		unset($session_lsCajax['bln_useRenderingFilter']);

        $session->set('lsCajax', $session_lsCajax);
		
		/*
		 * Don't do anything if we don't have cajax requestData, which means
		 * that the page is not being rendered in a cajax context
		 */
		if (!isset($tmp_ls_cajax['requestData']) || $tmp_ls_cajax['requestData'] === null) {
			return $str_content;
		}
		
		if (!($tmp_ls_cajax['requestData']['requestedElementID'] ?? null) && !($tmp_ls_cajax['requestData']['requestedElementClass'] ?? null)) {
			return $str_content;
		}

        $request = System::getContainer()->get('request_stack')->getCurrentRequest();

        if ($request && System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest($request))
        {
            $obj_beUser = \BackendUser::getInstance();
            if ($obj_beUser->currentLogin === null) {
                return 'NOT ALLOWED';
            }
        }

        /*
         * We create a dom document and load the original output html.
         */
        $obj_dom = new \DOMDocument();

        /*
         * Since DOMDocument will remove whitespaces between tags, we need to preserve them
         * by surrounding them with non-whitespace. We use "pws::" (meaning "preserve whitespace start")
         * and "::pws" (meaning "preserve whitespace end"). After DOM manipulation, we replace these
         * preserve whitespace markers with the original whitespace.
         */
        $str_content = preg_replace('/>(\s+)</', '>pws::$1::pws<', $str_content);



        /*
         * Replace ESI elements with random unique strings and store the mapping of the ESI elements to their
         * respective replacements in order to be able to re-insert the ESI elements later.
         * This is necessary because the ESI elements can make the html content invalid and thus prevent
         * DOMDocument from being able to parse it correctly.
         */
        $arr_esiElements = [];
        $str_content = preg_replace_callback(
            '/(<esi:include[^>]*>)/',

            function($matches) use (&$arr_esiElements) {
                $placeholder = '---esi-placeholder---' . uniqid('', true);
                $arr_esiElements[$placeholder] = $matches[0];
                return $placeholder;
            },

            $str_content
        );

        /*
         * Loading the original output html.
         * By using "mb_convert_encoding", we make sure that we have the correct encoding.
         * If the function "mb_convert_encoding" does not exist, we prepend an xml declaration
         * which should also work but doesn't under certain circumstances which is why it's
         * not our first choice.
         *
         */
        if (function_exists('mb_convert_encoding')) {
            @$obj_dom->loadHTML(mb_convert_encoding($str_content, 'HTML-ENTITIES', 'UTF-8'));
        } else {
            @$obj_dom->loadHTML('<?xml encoding="utf-8" ?>'.$str_content);
        }

        /*
         * We grab the nodes that match the requested element ids or classes and,
         * if the requested element could actually be found, overwrite the original
         * html with only the extracted node html.
         */
        $str_content = '';

        if ($tmp_ls_cajax['requestData']['requestedElementID'] ?? null) {
            $arr_requestedElementIds = array_map('trim', explode(',', $tmp_ls_cajax['requestData']['requestedElementID']));
            foreach ($arr_requestedElementIds as $str_requestedElementId) {
                $obj_relevantNode = $obj_dom->getElementById($str_requestedElementId);
                if ($obj_relevantNode !== null) {
                    # $str_content .= $this->getChildNodesAsHTMLString($obj_relevantNode);
                    $str_content .= $obj_relevantNode->ownerDocument->saveHTML($obj_relevantNode);
                }
            }
        }

        if ($tmp_ls_cajax['requestData']['requestedElementClass'] ?? null) {
            $arr_requestedElementClasses = array_map('trim', explode(',', $tmp_ls_cajax['requestData']['requestedElementClass']));
            $obj_xpath = new \DOMXPath($obj_dom);
            foreach ($arr_requestedElementClasses as $str_requestedElementClass) {
                $obj_relevantNodeList = $obj_xpath->query("//*[contains(concat(' ', normalize-space(@class), ' '), ' ".$str_requestedElementClass." ')]");
                if ($obj_relevantNodeList instanceof \DOMNode) {
                    $str_content .= $obj_relevantNodeList->ownerDocument->saveHTML($obj_relevantNodeList);
                } else if ($obj_relevantNodeList instanceof \DOMNodeList) {
                    foreach ($obj_relevantNodeList as $obj_relevantNode) {
                        if ($obj_relevantNode !== null) {
                            # $str_content .= $this->getChildNodesAsHTMLString($obj_relevantNode);
                            $str_content .= $obj_relevantNode->ownerDocument->saveHTML($obj_relevantNode);
                        }
                    }
                }
            }
        }

        // Re-insert ESI elements
        $str_content = str_replace(array_keys($arr_esiElements), array_values($arr_esiElements), $str_content);

        $str_content = preg_replace('/>pws::(\s+)::pws</', '>$1<', $str_content);

        /*
         * The html code may have been modified by DOMXPath to remove html tags, e.g. if they are redundant
         * (like two </p></p> in a row). In this case it can happen that the previous replacement didn't catch
         * all pws fragments. Therefore we now remove the leftovers.
         */
        $str_content = str_replace(array("::pws", "pws::"), "", $str_content);

        return $str_content;
    }

    /*
     * Gets the "inner html" of a node
     */
    protected function getChildNodesAsHTMLString($obj_node) {
        $str_content = '';
        foreach ($obj_node->childNodes as $obj_childNode) {
            $str_content .= $obj_node->ownerDocument->saveHTML($obj_childNode);
        }
        return $str_content;
    }

    protected function removeCacheBustingParameter() {
        /*
         * An ajax request might contain a random cache busting GET parameter that we don't want to have in case
         * of a cajax call.
         *
         * In a cajax call, regular contao modules most likely react as if the request was a regular non-ajax request
         * and they most likely don't expect this extra GET parameter and might misbehave if it is present. Therefore,
         * we get rid of it by removing it from the Environment's "request" property.
         *
         * We expect the random parameter to be a "valueless" parameter and identify it by this characteristic.
         */
        $arr_requestParts = explode('?', \Environment::get('request'));
        $str_requestBase = $arr_requestParts[0];
        $str_queryString = $arr_requestParts[1];

        $arr_queryStringParts = explode('&', $str_queryString);
        $arr_queryStringPartsToKeep = array();

        foreach ($arr_queryStringParts as $str_queryParameter) {
            if (strpos($str_queryParameter, '=') !== false) {
                $arr_queryStringPartsToKeep[] = $str_queryParameter;
            }
        }

        \Environment::set('request', $str_requestBase.(count($arr_queryStringPartsToKeep) ? '?'.implode('&', $arr_queryStringPartsToKeep) : ''));
    }
}
