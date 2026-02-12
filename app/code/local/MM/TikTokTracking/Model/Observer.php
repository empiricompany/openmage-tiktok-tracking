<?php

/**
 * MM_TikTokTracking Observer
 *
 * Intercepts system events to accumulate tracking data in session/registry
 * for Block consumption and JavaScript generation.
 */
class MM_TikTokTracking_Model_Observer
{
    /**
     * Process items added or removed from cart
     *
     * Event: sales_quote_item_save_after, sales_quote_item_delete_after
     *
     * @param Varien_Event_Observer $observer
     * @return void
     */
    public function processItemsAddedOrRemovedFromCart(Varien_Event_Observer $observer): void
    {
        /** @var Mage_Sales_Model_Quote_Item $item */
        $item = $observer->getEvent()->getItem();
        if ($item->getParentItem()) {
            return;
        }

        // avoid to process the same quote_item more than once
        // this could happen in case of double save of the same quote_item
        $processedProductsRegistry = Mage::registry('processed_quote_items_for_tiktok') ?? new ArrayObject();
        if ($processedProductsRegistry->offsetExists($item->getId())) {
            return;
        }
        $processedProductsRegistry[$item->getId()] = true;
        Mage::register('processed_quote_items_for_tiktok', $processedProductsRegistry, true);

        $addedQty = 0;
        $removedQty = 0;
        if ($item->isObjectNew()) {
            $addedQty = $item->getQty();
        } elseif ($item->isDeleted()) {
            $removedQty = $item->getQty();
        } elseif ($item->hasDataChanges()) {
            $newQty = $item->getQty();
            $oldQty = $item->getOrigData('qty');
            if ($newQty > $oldQty) {
                $addedQty = $newQty - $oldQty;
            } elseif ($newQty < $oldQty) {
                $removedQty = $oldQty - $newQty;
            }
        }

        if ($addedQty || $removedQty) {
            $product = $item->getProduct();
            $dataForAnalytics = [
                'sku' => $product->getSku(),
                'name' => $product->getName(),
                'qty' => $addedQty ?: $removedQty,
                'price' => $product->getFinalPrice(),
                'currency' => Mage::app()->getStore()->getCurrentCurrencyCode()
            ];

            $session = Mage::getSingleton('core/session');
            if ($addedQty) {
                $addedProducts = $session->getAddedProductsForTikTokAnalytics() ?: [];
                $addedProducts[] = $dataForAnalytics;
                $session->setAddedProductsForTikTokAnalytics($addedProducts);
            } else {
                $removedProducts = $session->getRemovedProductsForTikTokAnalytics() ?: [];
                $removedProducts[] = $dataForAnalytics;
                $session->setRemovedProductsForTikTokAnalytics($removedProducts);
            }
        }
    }

    /**
     * Track order success (Purchase event)
     *
     * Event: checkout_onepage_controller_success_action, checkout_multishipping_controller_success_action
     *
     * @param Varien_Event_Observer $observer
     * @return void
     */
    public function trackOrderSuccess(Varien_Event_Observer $observer)
    {
        $orderIds = $observer->getEvent()->getOrderIds();
        
        if (empty($orderIds) || !is_array($orderIds)) {
            return;
        }
        
        $session = Mage::getSingleton('core/session');
        
        foreach ($orderIds as $orderId) {
            $order = Mage::getModel('sales/order')->load($orderId);
            
            if (!$order->getId()) {
                continue;
            }
            
            // Save in session for Block to consume
            $session->setTikTokPurchaseOrder([
                'entity_id' => $order->getId(),
                'increment_id' => $order->getIncrementId(),
                'grand_total' => $order->getGrandTotal(),
                'order_currency_code' => $order->getOrderCurrencyCode(),
                'base_currency_code' => $order->getBaseCurrencyCode(),
                'customer_email' => $order->getCustomerEmail()
            ]);
        }
    }
}
