<?php

namespace LR\QuickQuote\Helper;

use Magento\Framework\App\Helper\Context;
use Magento\Customer\Model\Session as CustomerSession;
use Appseconnect\B2BMage\Helper\Mylist\Data as MyListDataHelper;
use Magento\Framework\Exception\LocalizedException;

class MyList extends \Magento\Framework\App\Helper\AbstractHelper
{
    /**
     * @var MyListDataHelper
     */
    protected $_myListHelper;

    /**
     * @var CustomerSession
     */
    protected $_customerSession;

    public function __construct(
        Context $context,
        CustomerSession $customerSession,
        MyListDataHelper $myListHelper
    ) {
        parent::__construct($context);

        $this->_customerSession = $customerSession;
        $this->_myListHelper = $myListHelper;
    }

    public function getCustomerMyListOptionHtml()
    {
        $result['status'] = false;
        try {
            if ($this->_customerSession->isLoggedIn()) {
                $lists = $this->_myListHelper->getListCollection();
                if ($lists && count($lists) > 0) {
                    $listOptions = [];
                    $listOptions[] = ['id' => '', 'name' => __('My Favourites')];
                    foreach ($lists as $list) {
                        $listOptions[] = ['id' => $list->getId(), 'name' => $list->getListName()];
                    }
                    $result['list'] = $listOptions;
                    $result['status'] = true;
                }
            }
        } catch (LocalizedException $e) {
            $result['message'] = $e->getMessage();
        } catch (\Exception $e) {
            $result['message'] = __('An error occurred while processing your request. Please try again later.');
        }
        return $result;
    }
}
