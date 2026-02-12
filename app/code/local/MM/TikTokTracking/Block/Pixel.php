<?php

/**
 * MM_TikTokTracking Pixel Block
 *
 * Generates TikTok Pixel JavaScript code for various e-commerce events.
 */
class MM_TikTokTracking_Block_Pixel extends Mage_Core_Block_Template
{
    /**
     * @var MM_TikTokTracking_Helper_Data
     */
    protected $_helper;
    
    /**
     * Constructor
     */
    protected function _construct()
    {
        parent::_construct();
        $this->_helper = Mage::helper('mm_tiktok_tracking');
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
        $action = $request->getActionName();
        $helper = $this->_helper;
        
        // AddToCart event (Session-based) - tracked on any page
        $addedProducts = Mage::getSingleton('core/session')->getAddedProductsForTikTokAnalytics();
        if ($addedProducts) {
            foreach ($addedProducts as $_addedProduct) {
                $validation = $helper->validateProductData($_addedProduct);
                if ($validation === true) {
                    $eventData = [
                        'content_type' => 'product',
                        'content_id' => $_addedProduct['sku'],
                        'content_name' => $_addedProduct['name'],
                        'value' => (float)number_format($_addedProduct['price'] * $_addedProduct['qty'], 2, '.', ''),
                        'quantity' => (int) $_addedProduct['qty'],
                        'currency' => $_addedProduct['currency']
                    ];
                    $result[] = ['AddToCart', $eventData];
                }
            }
            Mage::getSingleton('core/session')->unsAddedProductsForTikTokAnalytics();
        }
        
        // ViewContent event (Product page)
        if ($moduleName == 'catalog' && $controllerName == 'product') {
            $productData = $helper->getCurrentProductData();
            if ($productData) {
                $validation = $helper->validateProductData($productData);
                if ($validation === true) {
                    $eventData = [
                        'content_type' => 'product',
                        'content_id' => $productData['sku'],
                        'content_name' => $productData['name'],
                        'value' => (float)number_format($productData['price'], 2, '.', ''),
                        'currency' => $productData['currency']
                    ];
                    $result[] = ['ViewContent', $eventData];
                }
            }
        }
        
        // InitiateCheckout event (Checkout page)
        if (($moduleName == 'checkout' && $controllerName == 'onepage') || 
            ($moduleName == 'firecheckout' && $controllerName == 'index')) {
            $quote = Mage::getSingleton('checkout/session')->getQuote();
            if ($quote && $quote->getId()) {
                $items = $quote->getAllVisibleItems();
                if (!empty($items)) {
                    $contentIds = [];
                    $value = 0.00;
                    foreach ($items as $item) {
                        $contentIds[] = $item->getSku();
                        $value += $item->getBasePriceInclTax() * $item->getQty();
                    }
                    $eventData = [
                        'content_type' => 'product_group',
                        'content_id' => $contentIds,
                        'value' => (float)number_format($value, 2, '.', ''),
                        'currency' => Mage::app()->getStore()->getCurrentCurrencyCode()
                    ];
                    $result[] = ['InitiateCheckout', $eventData];
                }
            }
        }
        
        // Purchase event (Success page - CRITICAL deduplication)
        if ($moduleName == 'checkout' && $controllerName == 'onepage' && $action == 'success') {
            $session = Mage::getSingleton('core/session');
            $orderData = $session->getTikTokPurchaseOrder();
            
            if ($orderData && isset($orderData['entity_id'])) {
                $order = Mage::getModel('sales/order')->load($orderData['entity_id']);
                if ($order && $order->getId()) {
                    $contentIds = [];
                    foreach ($order->getAllVisibleItems() as $item) {
                        $contentIds[] = $item->getSku();
                    }
                    
                    if (!empty($contentIds)) {
                        $eventData = [
                            'content_type' => 'product_group',
                            'content_id' => $contentIds,
                            'value' => (float)number_format($order->getGrandTotal(), 2, '.', ''),
                            'currency' => $order->getBaseCurrencyCode(),
                            'order_id' => $order->getIncrementId()
                        ];
                        $result[] = ['Purchase', $eventData];
                    }
                }
                // CRITICAL: Clear session (deduplication)
                $session->unsTikTokPurchaseOrder();
            }
        }
        
        // Advanced Matching - add identify calls if enabled
        if ($helper->isAdvancedMatchingEnabled() && !empty($result)) {
            $customer = Mage::getSingleton('customer/session')->getCustomer();
            if ($customer && $customer->getId() && $customer->getEmail()) {
                $email = $helper->hashEmail($customer->getEmail());
                if ($email) {
                    $identifyData = [
                        'email' => $email
                    ];
                    $result[] = ['Identify', $identifyData];
                }
            }
        }
        
        // Convert result array to ttq.track() calls
        $scripts = '';
        foreach ($result as $event) {
            $eventName = $event[0];
            $eventData = $event[1];
            
            // Handle Identify event differently
            if ($eventName === 'Identify') {
                $scripts .= "ttq.identify(" . json_encode($eventData, JSON_THROW_ON_ERROR) . ");" . "\n";
            } else {
                $scripts .= "ttq.track('{$eventName}', " . json_encode($eventData, JSON_THROW_ON_ERROR) . ");" . "\n";
            }
        }
        
        return $scripts;
    }
}
