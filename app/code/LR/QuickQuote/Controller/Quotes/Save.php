<?php

namespace LR\QuickQuote\Controller\Quotes;

use \Magento\Framework\Exception\LocalizedException;

class Save extends \Magento\Framework\App\Action\Action
{
	
	protected $quoteModel;


    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \LR\QuickQuote\Model\Quotes $quoteModel
    ) {
        parent::__construct($context);
		$this->quoteModel              = $quoteModel;
    }

    public function execute()
    {
		$result['status'] = false;
		$data             = $this->getRequest()->getPostValue();
		$storeId          = $this->_objectManager->get(\Magento\Store\Model\StoreManagerInterface::class)->getStore()->getId();
        if ($data && isset($data['quote_data'])) 
		{
            try 
			{
            	$valid = true;
				$recordData = [];
				$recordData['sales_rep_id'] = 2;
				$recordData['cust_id'] = 3;
				$recordData['items_data'] = $data['quote_data'];

				$this->quoteModel->setData($recordData)->save();
				$result['status']      = true;
				$result['redirectUrl'] = $this->_url->getUrl('quick-quote');
				$result['message'] = __('Your Quote has been saved successfully!.');
				
            } catch (LocalizedException $e) {
                $result['message'] = $e->getMessage();
            } catch (\Exception $e) {
            	$result['message'] = __('Something went wrong while processing the request.');
            }
        }
        $this->getResponse()->representJson(
            $this->_objectManager->get(\Magento\Framework\Json\Helper\Data::class)->jsonEncode($result)
        );
        return;
    }
}