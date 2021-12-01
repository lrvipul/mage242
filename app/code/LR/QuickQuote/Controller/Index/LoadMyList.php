<?php

namespace LR\QuickQuote\Controller\Index;

class LoadMyList extends \Magento\Framework\App\Action\Action
{

    protected $_customerSession;

    protected $_mylistHelper;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Customer\Model\Session $customerSession,
        \Appseconnect\B2BMage\Helper\Mylist\Data $mylistHelper
    ) {
        parent::__construct($context);
        $this->_customerSession = $customerSession;
        $this->_mylistHelper = $mylistHelper;
    }

    /**
     * @return \Magento\Framework\App\ResponseInterface|\Magento\Framework\Controller\ResultInterface|void
     */
    public function execute()
    {
    	$result['status'] = false;
    	try {
    	    if ($this->_customerSession->isLoggedIn()) {
    	        $lists = $this->_mylistHelper->getListCollection();
                if ($lists && count($lists) > 0) {
                    $_listsHtml = '<select id="add-mylist-id" name="add-mylist-id" class="validate-select">';
                    $_listsHtml .= '<option value="">Select List</option>';
                    foreach ($lists as $list) {
                        $_listsHtml .= '<option value="' . $list->getId() . '">' . $list->getListName() . '</option>';;
                    }
                    $_listsHtml .= '</select>';
                    $result['list'] = $_listsHtml;
                    $result['status'] = true;
                }
            }
    	} catch (\Magento\Framework\Exception\LocalizedException $e) {
            $result['message'] = $e->getMessage();
        } catch (\Exception $e) {
        	$result['message'] = __('An error occurred while processing your request. Please try again later.');
        }
    	$this->getResponse()->representJson(
            $this->_objectManager->get(\Magento\Framework\Json\Helper\Data::class)->jsonEncode($result)
        );
    }
}