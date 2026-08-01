<?php

/**
 * MM_TikTokTracking Pixel Block
 *
 * Generates TikTok Pixel JavaScript code for various e-commerce events.
 */
class MM_TikTokTracking_Block_Pixel extends Mage_Core_Block_Template
{
    /**
     * Supported checkout pages for InitiateCheckout event
     */
    protected const CHECKOUT_PAGES = [
        ['module' => 'checkout', 'controller' => 'onepage'],
        ['module' => 'firecheckout', 'controller' => 'index']
    ];

    /**
     * @var MM_TikTokTracking_Helper_Data
     */
    protected $_helper;

    /**
     * @var array
     */
    protected $_orderIds = [];

    /**
     * Constructor
     */
    protected function _construct()
    {
        parent::_construct();
        $this->_helper = Mage::helper('mm_tiktok_tracking');
    }

    /**
     * Set order IDs for Purchase event tracking
     *
     * @param array $orderIds
     * @return $this
     */
    public function setOrderIds($orderIds)
    {
        $this->_orderIds = $orderIds;
        return $this;
    }

    /**
     * Get order IDs
     *
     * @return array
     */
    public function getOrderIds()
    {
        return $this->_orderIds;
    }

    /**
     * Get TikTok Pixel base script (initialization)
     *
     * @param string $pixelId
     * @return string
     */
    protected function _getBaseScript($pixelId)
    {
        $pixelId = $this->jsQuoteEscape($pixelId);

        return <<<TIKTOK
!function (w, d, t) {
  w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var i="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=i,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};var o=document.createElement("script");o.type="text/javascript",o.async=!0,o.src=i+"?sdkid="+e+"&lib="+t;var a=document.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};

  ttq.load('{$pixelId}');
  ttq.page();
}(window, document, 'ttq');
TIKTOK;
    }

    /**
     * Get TikTok Pixel events scripts
     *
     * Build data array, then convert to ttq.track() calls
     *
     * @return string
     */
    public function _getTikTokEventsScript()
    {
        if (!$this->_helper->isEnabled()) {
            return '';
        }

        // Skip event tracking on AJAX requests
        // AJAX requests render full page but only parts are displayed, so don't consume session data
        // Data will be consumed on the actual page view (non-AJAX)
        // Check both XMLHttpRequest and Alpine.js X-Alpine-Request header
        $isXmlHttpRequest = $this->getRequest()->isXmlHttpRequest();
        $isAlpineRequest = $this->getRequest()->getHeader('X-Alpine-Request');
        $isAjax = $isXmlHttpRequest || $isAlpineRequest;

        if ($isAjax) {
            return '';
        }

        $result = [];
        $request = $this->getRequest();
        $moduleName = $request->getModuleName();
        $controllerName = $request->getControllerName();
        $helper = $this->_helper;

        // AddToCart event (Session-based) - tracked on any page
        $addedProducts = Mage::getSingleton('core/session')->getAddedProductsForTikTokAnalytics();
        if ($addedProducts) {
            foreach ($addedProducts as $_addedProduct) {
                $validation = $helper->validateProductData($_addedProduct);
                if ($validation === true) {
                    $eventData = [
                        'contents' => [
                            [
                                'content_id' => $_addedProduct['sku'],
                                'content_name' => $_addedProduct['name'],
                                'quantity' => (int) $_addedProduct['qty'],
                                'price' => (float)number_format($_addedProduct['price'], 2, '.', '')
                            ]
                        ],
                        'content_type' => 'product',
                        'value' => (float)number_format($_addedProduct['price'] * $_addedProduct['qty'], 2, '.', ''),
                        'currency' => $_addedProduct['currency']
                    ];
                    $result[] = ['AddToCart', $eventData];
                }
                Mage::getSingleton('core/session')->unsAddedProductsForTikTokAnalytics();
            }
        }

        // ViewContent event (Product page)
        if ($moduleName == 'catalog' && $controllerName == 'product') {
            $productViewed = Mage::registry('current_product');
            if ($productViewed && $productViewed->getId()) {
                $priceInclTax = (float) Mage::helper('tax')->getPrice($productViewed, $productViewed->getFinalPrice(), true);
                $eventData = [
                    'contents' => [
                        [
                            'content_id' => $productViewed->getSku(),
                            'content_name' => $productViewed->getName(),
                            'price' => (float)number_format($priceInclTax, 2, '.', '')
                        ]
                    ],
                    'content_type' => 'product',
                    'value' => (float)number_format($priceInclTax, 2, '.', ''),
                    'currency' => Mage::app()->getStore()->getCurrentCurrencyCode()
                ];
                $result[] = ['ViewContent', $eventData];
            }
        }

        // InitiateCheckout event (Checkout page)
        $isCheckoutPage = false;
        foreach (static::CHECKOUT_PAGES as $page) {
            if ($moduleName == $page['module'] && $controllerName == $page['controller']) {
                $isCheckoutPage = true;
                break;
            }
        }

        if ($isCheckoutPage) {
            $quote = Mage::getSingleton('checkout/session')->getQuote();
            if ($quote && $quote->getId()) {
                $items = $quote->getAllVisibleItems();
                if (!empty($items)) {
                    $contents = [];
                    $value = 0.00;
                    foreach ($items as $item) {
                        $itemPrice = (float)$item->getPriceInclTax();
                        $product = $item->getProduct();
                        $sku = ($product && $product->getId()) ? $product->getSku() : $item->getSku();
                        $contents[] = [
                            'content_id' => $sku,
                            'content_name' => $item->getName(),
                            'quantity' => (int) $item->getQty(),
                            'price' => (float)number_format($itemPrice, 2, '.', '')
                        ];
                        $value += $itemPrice * $item->getQty();
                    }
                    $eventData = [
                        'contents' => $contents,
                        'content_type' => 'product',
                        'value' => (float)number_format($value, 2, '.', ''),
                        'currency' => Mage::app()->getStore()->getCurrentCurrencyCode()
                    ];
                    $result[] = ['InitiateCheckout', $eventData];
                }
            }
        }

        // Purchase event
        $orderIds = $this->getOrderIds();
        $guestEmail = null;
        if (!empty($orderIds) && is_array($orderIds)) {
            $collection = Mage::getResourceModel('sales/order_collection')
                ->addFieldToFilter('entity_id', ['in' => $orderIds]);

            foreach ($collection as $order) {
                $contents = [];

                if (!$guestEmail && $order->getCustomerEmail()) {
                    $guestEmail = $order->getCustomerEmail();
                }

                foreach ($order->getAllItems() as $item) {
                    if ($item->getParentItem()) {
                        continue;
                    }
                    $product = $item->getProduct();
                    $sku = ($product && $product->getId()) ? $product->getSku() : $item->getSku();
                    $contents[] = [
                        'content_id' => $sku,
                        'content_name' => $item->getName(),
                        'quantity' => (int) $item->getQtyOrdered(),
                        'price' => (float)number_format($item->getBasePriceInclTax(), 2, '.', '')
                    ];
                }

                if (!empty($contents)) {
                    $eventData = [
                        'contents' => $contents,
                        'content_type' => 'product',
                        'value' => (float)number_format($order->getBaseGrandTotal(), 2, '.', ''),
                        'currency' => $order->getBaseCurrencyCode()
                    ];
                    $result[] = ['Purchase', $eventData];
                }
            }
        }

        // Advanced Matching
        if ($helper->isAdvancedMatchingEnabled() && !empty($result)) {
            $email = null;

            $customer = Mage::getSingleton('customer/session')->getCustomer();
            if ($customer && $customer->getId() && $customer->getEmail()) {
                $email = $customer->getEmail();
            }

            if (!$email && $guestEmail) {
                $email = $guestEmail;
            }

            if ($email) {
                $hashedEmail = $helper->hashEmail($email);
                if ($hashedEmail) {
                    $result[] = ['Identify', ['email' => $hashedEmail]];
                }
            }
        }

        if (empty($result)) {
            return '';
        }

        if ($this->helper('mm_tiktok_tracking')->isDebugModeEnabled()) {
            $this->helper('mm_tiktok_tracking')->log($result);
        }

        // Sort result array so Identify events come before track events
        usort($result, [$this, '_sortEventsCallback']);

        // Convert result array to ttq calls
        $scripts = '';
        foreach ($result as $event) {
            $eventName = $event[0];
            $eventData = $event[1];

            if ($eventName === 'Identify') {
                $scripts .= "ttq.identify(" . json_encode($eventData, JSON_THROW_ON_ERROR) . ");" . "\n";
            } else {
                $scripts .= "ttq.track('{$eventName}', " . json_encode($eventData, JSON_THROW_ON_ERROR) . ");" . "\n";
            }
        }

        return $scripts;
    }

    /**
     * Sort callback: Identify events sort before all other events
     *
     * @param array $a
     * @param array $b
     * @return int
     */
    private function _sortEventsCallback(array $a, array $b)
    {
        $aPosition = ($a[0] === 'Identify') ? 0 : 1;
        $bPosition = ($b[0] === 'Identify') ? 0 : 1;
        return $aPosition - $bPosition;
    }
}
