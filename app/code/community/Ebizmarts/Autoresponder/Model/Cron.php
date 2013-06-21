<?php
/**
 * Author : Ebizmarts <info@ebizmarts.com>
 * Date   : 6/14/13
 * Time   : 5:05 PM
 * File   : Cron.php
 * Module : Ebizmarts_Magemonkey
 */
class Ebizmarts_Autoresponder_Model_Cron
{
    /**
     *
     */
    public function process()
    {
        $allStores = Mage::app()->getStores();
        foreach($allStores as $storeId => $val)
        {
            if(Mage::getStoreConfig(Ebizmarts_AbandonedCart_Model_Config::ACTIVE,$storeId)) {
                $this->_processStore($storeId);
            }
        }
    }

    /**
     * @param $storeId
     */
    protected function _processStore($storeId)
    {
        if(Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::NEWORDER_ACTIVE,$storeId)) { // done
            $this->_processNewOrders($storeId);
        }
        if(Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::RELATED_ACTIVE,$storeId)) { // done
            $this->_processRelated($storeId);
        }
        if(Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::REVIEW_ACTIVE,$storeId)) { // done
            $this->_processReview($storeId);
        }
        if(Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::BIRTHDAY_ACTIVE,$storeId)) { // done
            $this->_processBirthday($storeId);
        }
        if(Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::REGISTRATION_ACTIVE,$storeId)) {
            $this->_processRegistration($storeId);
        }
        if(Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::NEWSLETTER_ACTIVE,$storeId)) {
            $this->_processSubscription($storeId);
        }
        if(Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::NOACTIVITY_ACTIVE,$storeId)) { // done
            $this->_processNoActivity($storeId);
        }
        if(Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::WISHLIST_ACTIVE,$storeId)) {
            $this->_processWishlist($storeId);
        }
    }
    protected function _processNewOrders($storeId)
    {
        Mage::log(__METHOD__);
        $customerGroups = explode(",",Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::NEWORDER_CUSTOMER_GROUPS, $storeId));
        $days           = Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::NEWORDER_DAYS,$storeId);
        $tags           = Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::NEWORDER_MANDRILL_TAG,$storeId)."_$storeId";
        $adapter        = Mage::getSingleton('core/resource')->getConnection('sales_read');
        $mailSubject    = Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::NEWORDER_SUBJECT,$storeId);
        $senderId       = Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::GENERAL_SENDER,$storeId);
        $sender         = array('name'=>Mage::getStoreConfig("trans_email/ident_$senderId/name"), 'email'=> Mage::getStoreConfig("trans_email/ident_$senderId/email"));
        $templateId     = Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::NEWORDER_TEMPLATE,$storeId);

        $expr = sprintf('DATE_SUB(%s, %s)', $adapter->quote(now()), $this->_getIntervalUnitSql($days, 'DAY'));
        $from = new Zend_Db_Expr($expr);
        $expr = sprintf('DATE_SUB(%s, %s)', $adapter->quote(now()), $this->_getIntervalUnitSql($days-1, 'DAY'));
        $to = new Zend_Db_Expr($expr);
        $collection = Mage::getResourceModel('sales/order_collection');
        $collection->addFieldToFilter('main_table.store_id',array('eq'=>$storeId))
                    ->addFieldToFilter('main_table.created_at',array('from'=>$from,'to'=>$to));
        if(count($customerGroups)) {
            $collection->addFieldToFilter('main_table.customer_group_id',array('in'=> $customerGroups));
        }
        Mage::log((string)$collection->getSelect());
        foreach($collection as $order) {
            $translate = Mage::getSingleton('core/translate');
            $email = $order->getCustomerEmail();
            $name = $order->getCustomerFirstname().' '.$order->getCustomerLastname();
            $vars = array('tags'=>array($tags));

            $mail = Mage::getModel('core/email_template')->setTemplateSubject($mailSubject)->sendTransactional($templateId,$sender,$email,$name,$vars,$storeId);
            $translate->setTranslateInLine(true);

        }
    }
    protected function _processBirthday($storeId)
    {
        Mage::log(__METHOD__);
        $days           = Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::BIRTHDAY_DAYS,$storeId);
        $customerGroups = explode(",",Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::BIRTHDAY_CUSTOMER_GROUPS, $storeId));
        $senderId       = Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::GENERAL_SENDER,$storeId);
        $sender         = array('name'=>Mage::getStoreConfig("trans_email/ident_$senderId/name"), 'email'=> Mage::getStoreConfig("trans_email/ident_$senderId/email"));
        $templateId     = Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::BIRTHDAY_TEMPLATE,$storeId);
        $mailSubject    = Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::BIRTHDAY_SUBJECT,$storeId);
        $tags           = Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::BIRTHDAY_MANDRILL_TAG,$storeId)."_$storeId";
        $sendCoupon     = Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::BIRTHDAY_COUPON,$storeId);
        $customerGroupsCoupon = explode(",",Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::BIRTHDAY_CUSTOMER_COUPON, $storeId));


        $adapter        = Mage::getSingleton('core/resource')->getConnection('sales_read');
        $expr           = sprintf('DATE_SUB(%s, %s)', $adapter->quote(now()), $this->_getIntervalUnitSql($days, 'DAY'));
        $from           = new Zend_Db_Expr($expr);
        $expr           = sprintf('DATE_SUB(%s, %s)', $adapter->quote(now()), $this->_getIntervalUnitSql($days-1, 'DAY'));
        $to             = new Zend_Db_Expr($expr);
        $collection     = Mage::getModel('customer/customer')->getCollection();
        $date = date("Y-m-d H:i:s");
        $date2 = date("Y-m-d H:i:s",strtotime(" - $days days"));
        $month = date("m",strtotime($date2));
        $day = date("d",strtotime($date2));
        $moreselect = "MONTH(at_dob.value) = $month AND DAY(at_dob.value) = $day";


        $collection->addAttributeToFilter('dob',array('neq'=>'null'))
                    ->addFieldToFilter('store_id',array('eq'=>$storeId));
        if(count($customerGroups)) {
            $collection->addFieldToFilter('group_id',array('in'=>$customerGroups));
        }
        $collection->getSelect()->where($moreselect);
        foreach($collection as $customer) {
            $translate = Mage::getSingleton('core/translate');
            $email = $customer->getEmail();
            $name = $customer->getFirstname().' '.$customer->getLastname();
            $vars = array();
            if($sendCoupon && in_array($customer->getGroupId(),$customerGroupsCoupon)) {
                if(Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::BIRTHDAY_AUTOMATIC,$storeId)==Ebizmarts_Autoresponder_Model_Config::COUPON_AUTOMATIC) {
                    list($couponcode,$discount,$toDate) = $this->_createNewCoupon($storeId,$email);
                    $vars = array('couponcode'=>$couponcode,'discount' => $discount, 'todate' => $toDate, 'name' => $name,'tags'=>array($tags));
                }
                else {
                    $couponcode = Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::BIRTHDAY_COUPON_CODE);
                    $vars = array('couponcode'=>$couponcode, 'name' => $name,'tags'=>array($tags));
                }

            }
            $mail = Mage::getModel('core/email_template')->setTemplateSubject($mailSubject)->sendTransactional($templateId,$sender,$email,$name,$vars,$storeId);
            $translate->setTranslateInLine(true);
        }

    }
    protected function _processNoActivity($storeId)
    {
        Mage::log(__METHOD__);
        $days           = Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::NOACTIVITY_DAYS,$storeId);
        $customerGroups = explode(",",Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::NOACTIVITY_CUSTOMER_GROUPS, $storeId));
        $senderId       = Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::GENERAL_SENDER,$storeId);
        $sender         = array('name'=>Mage::getStoreConfig("trans_email/ident_$senderId/name"), 'email'=> Mage::getStoreConfig("trans_email/ident_$senderId/email"));
        $templateId     = Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::NOACTIVITY_TEMPLATE,$storeId);
        $mailSubject    = Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::NOACTIVITY_SUBJECT,$storeId);
        $tags           = Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::NOACTIVITY_MANDRILL_TAG,$storeId)."_$storeId";

        $collection     = Mage::getModel('customer/customer')->getCollection();


        if(count($customerGroups)) {
            $collection->addFieldToFilter('group_id',array('in'=>$customerGroups));
        }
        $collection->addFieldToFilter('store_id',array('eq'=>$storeId));

        foreach($collection as $customer) {
            $customerId = $customer->getEntityId();
            // get the orders for this customer for this store
            $orderCollection = Mage::getModel('sales/order')->getCollection();
            $orderCollection->addFieldToFilter('customer_id',array('eq'=>$customerId))
                            ->addFieldToFilter('store_id',array('eq'=>$storeId));
            if($orderCollection->getSize()>0) { // if the customer has any order for this store
                $logCustomer = Mage::getModel('log/customer')->loadByCustomer($customer);
                $lastVisited = $logCustomer->getLoginAt();
                $limit = date("Y-m-d H:i:s",strtotime(" - $days days"));
                if($limit>$lastVisited) {
                    $translate = Mage::getSingleton('core/translate');
                    $email = $customer->getEmail();
                    $name = $customer->getFirstname().' '.$customer->getLastname();
                    $vars = array('name' => $name,'tags'=>array($tags),'lastlogin'=>$lastVisited);
                    $mail = Mage::getModel('core/email_template')->setTemplateSubject($mailSubject)->sendTransactional($templateId,$sender,$email,$name,$vars,$storeId);
                    $translate->setTranslateInLine(true);
                }
            }
        }

    }
    protected function _processRelated($storeId)
    {
        Mage::log(__METHOD__);
        $customerGroups = explode(",",Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::RELATED_CUSTOMER_GROUPS, $storeId));
        $days           = Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::RELATED_DAYS,$storeId);
        $tags           = Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::RELATED_MANDRILL_TAG,$storeId)."_$storeId";
        $adapter        = Mage::getSingleton('core/resource')->getConnection('sales_read');
        $mailSubject    = Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::RELATED_SUBJECT,$storeId);
        $senderId       = Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::GENERAL_SENDER,$storeId);
        $sender         = array('name'=>Mage::getStoreConfig("trans_email/ident_$senderId/name"), 'email'=> Mage::getStoreConfig("trans_email/ident_$senderId/email"));
        $templateId     = Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::RELATED_TEMPLATE,$storeId);
        $maxRelated     = Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::RELATED_MAX,$storeId);

        $expr = sprintf('DATE_SUB(%s, %s)', $adapter->quote(now()), $this->_getIntervalUnitSql($days, 'DAY'));
        $from = new Zend_Db_Expr($expr);
        $expr = sprintf('DATE_SUB(%s, %s)', $adapter->quote(now()), $this->_getIntervalUnitSql($days-1, 'DAY'));
        $to = new Zend_Db_Expr($expr);
        $collection = Mage::getResourceModel('sales/order_collection');
        $collection->addFieldToFilter('main_table.store_id',array('eq'=>$storeId))
            ->addFieldToFilter('main_table.created_at',array('from'=>$from,'to'=>$to));
        if(count($customerGroups)) {
            $collection->addFieldToFilter('main_table.customer_group_id',array('in'=> $customerGroups));
        }
        foreach($collection as $order) {
            $counter = 0;
            $allRelated = array();
            foreach($order->getAllItems() as $itemId => $item) {
                if($maxRelated && $maxRelated < $counter) {
                    break;
                }
                $product = Mage::getModel('catalog/product')->load($item->getProductId());
                foreach($product->getRelatedLinkCollection() as $related) {
                    if($maxRelated && $maxRelated < $counter) {
                        break;
                    }
                    $relatedProduct = Mage::getModel('catalog/product')->load($related->getLinkedProductId());
                    $allRelated[$counter++] = $relatedProduct;
                }
            }
            if($counter > 0) {
                $translate = Mage::getSingleton('core/translate');
                $email = $order->getCustomerEmail();
                $name = $order->getCustomerFirstname().' '.$order->getCustomerLastname();
                $vars = array('name' => $name,'tags'=>array($tags),'related'=>$allRelated);
                $mail = Mage::getModel('core/email_template')->setTemplateSubject($mailSubject)->sendTransactional($templateId,$sender,$email,$name,$vars,$storeId);
                $translate->setTranslateInLine(true);
            }
        }

    }
    protected function _processReview($storeId)
    {
        Mage::log(__METHOD__);
        $customerGroups = explode(",",Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::REVIEW_CUSTOMER_GROUPS, $storeId));
        $days           = Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::REVIEW_DAYS,$storeId);
        $tags           = Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::REVIEW_MANDRILL_TAG,$storeId)."_$storeId";
        $adapter        = Mage::getSingleton('core/resource')->getConnection('sales_read');
        $mailSubject    = Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::REVIEW_SUBJECT,$storeId);
        $senderId       = Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::GENERAL_SENDER,$storeId);
        $sender         = array('name'=>Mage::getStoreConfig("trans_email/ident_$senderId/name"), 'email'=> Mage::getStoreConfig("trans_email/ident_$senderId/email"));
        $templateId     = Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::REVIEW_TEMPLATE,$storeId);

        $expr = sprintf('DATE_SUB(%s, %s)', $adapter->quote(now()), $this->_getIntervalUnitSql($days, 'DAY'));
        $from = new Zend_Db_Expr($expr);
        $expr = sprintf('DATE_SUB(%s, %s)', $adapter->quote(now()), $this->_getIntervalUnitSql($days-1, 'DAY'));
        $to = new Zend_Db_Expr($expr);
        $collection = Mage::getResourceModel('sales/order_collection');
        $collection->addFieldToFilter('main_table.store_id',array('eq'=>$storeId))
            ->addFieldToFilter('main_table.created_at',array('from'=>$from,'to'=>$to));
        if(count($customerGroups)) {
            $collection->addFieldToFilter('main_table.customer_group_id',array('in'=> $customerGroups));
        }
        foreach($collection as $order) {
            $translate = Mage::getSingleton('core/translate');
            $email = $order->getCustomerEmail();
            $name = $order->getCustomerFirstname().' '.$order->getCustomerLastname();
            $products = array();
            foreach($order->getAllItems() as $item) {
                $products[] = Mage::getModel('catalog/product')->load($item->getProductId());
            }
            $orderNum = $order->getIncrementId();
            $vars = array('name' => $name,'tags'=>array($tags),'products'=>$products,'ordernum'=>$orderNum);
            $mail = Mage::getModel('core/email_template')->setTemplateSubject($mailSubject)->sendTransactional($templateId,$sender,$email,$name,$vars,$storeId);
            $translate->setTranslateInLine(true);
        }

    }
    protected function _processRegistration($storeId)
    {

    }
    protected function _processSubscription($storeId)
    {

    }
    protected function _processWishlist($storeId)
    {
        Mage::log(__METHOD__);
        $customerGroups = explode(",",Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::WISHLIST_CUSTOMER_GROUPS, $storeId));
        $days           = Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::WISHLIST_DAYS,$storeId);
        $tags           = Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::WISHLIST_MANDRILL_TAG,$storeId)."_$storeId";
        $adapter        = Mage::getSingleton('core/resource')->getConnection('sales_read');
        $mailSubject    = Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::WISHLIST_SUBJECT,$storeId);
        $senderId       = Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::GENERAL_SENDER,$storeId);
        $sender         = array('name'=>Mage::getStoreConfig("trans_email/ident_$senderId/name"), 'email'=> Mage::getStoreConfig("trans_email/ident_$senderId/email"));
        $templateId     = Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::WISHLIST_TEMPLATE,$storeId);

        $expr = sprintf('DATE_SUB(%s, %s)', $adapter->quote(now()), $this->_getIntervalUnitSql($days, 'DAY'));
        $from = new Zend_Db_Expr($expr);
        $expr = sprintf('DATE_SUB(%s, %s)', $adapter->quote(now()), $this->_getIntervalUnitSql($days-1, 'DAY'));
        $to = new Zend_Db_Expr($expr);

        $collection = Mage::getModel('wishlist/item')->getCollection();
        $collection->addFieldToFilter('main_table.added_at',array('from'=>$from,'to'=>$to));
        Mage::log((string)$collection->getSelect());
        foreach($collection as $item) {
            $customer = Mage::getModel('customer/customer')->load($item->getCustomerId());
            if(in_array($customer->getGroupId(),$customerGroups)) {
                $translate = Mage::getSingleton('core/translate');
                $email = $customer->getEmail();
                $name = $customer->getFirstname().' '.$customer->getLastname();
                $product = Mage::getModel('catalog/product')->load($item->getProductId());
                $vars = array('name' => $name,'tags'=>array($tags),'product'=>$product);
                $mail = Mage::getModel('core/email_template')->setTemplateSubject($mailSubject)->sendTransactional($templateId,$sender,$email,$name,$vars,$storeId);
                $translate->setTranslateInLine(true);

            }
        }

    }
    protected function _createNewCoupon($store,$email)
    {
        $couponamount = Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::BIRTHDAY_DISCOUNT, $store);
        $couponexpiredays = Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::BIRTHDAY_EXPIRE, $store);
        $coupontype = Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::BIRTHDAY_DISCOUNT_TYPE, $store);
        $couponlength = Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::BIRTHDAY_LENGTH, $store);
        $couponlabel = Mage::getStoreConfig(Ebizmarts_Autoresponder_Model_Config::BIRTHDAY_COUPON_LABEL, $store);
        $websiteid =  Mage::getModel('core/store')->load($store)->getWebsiteId();

        $fromDate = date("Y-m-d");
        $toDate = date('Y-m-d', strtotime($fromDate. " + $couponexpiredays day"));
        if($coupontype == 1) {
            $action = 'cart_fixed';
            $discount = Mage::app()->getStore($store)->getCurrentCurrencyCode()."$couponamount";
        }
        elseif($coupontype == 2) {
            $action = 'by_percent';
            $discount = "$couponamount%";
        }
        $customer_group = new Mage_Customer_Model_Group();
        $allGroups  = $customer_group->getCollection()->toOptionHash();
        $groups = array();
        foreach($allGroups as $groupid=>$name) {
            $groups[] = $groupid;
        }
        $coupon_rule = Mage::getModel('salesrule/rule');
        $coupon_rule->setName("Birthday coupon $email")
            ->setDescription("Birthday coupon $email")
            ->setFromDate($fromDate)
            ->setToDate($toDate)
            ->setIsActive(1)
            ->setCouponType(2)
            ->setUsesPerCoupon(1)
            ->setUsesPerCustomer(1)
            ->setCustomerGroupIds($groups)
            ->setProductIds('')
            ->setLengthMin($couponlength)
            ->setLengthMax($couponlength)
            ->setSortOrder(0)
            ->setStoreLabels(array($couponlabel))
            ->setSimpleAction($action)
            ->setDiscountAmount($couponamount)
            ->setDiscountQty(0)
            ->setDiscountStep('0')
            ->setSimpleFreeShipping('0')
            ->setApplyToShipping('0')
            ->setIsRss(0)
            ->setWebsiteIds($websiteid);
        $uniqueId = Mage::getSingleton('salesrule/coupon_codegenerator', array('length' => $couponlength))->generateCode();
        $coupon_rule->setCouponCode($uniqueId);
        $coupon_rule->save();
        return array($uniqueId,$discount,$toDate);
    }

    function _getIntervalUnitSql($interval, $unit)
    {
        return sprintf('INTERVAL %d %s', $interval, $unit);
    }

}