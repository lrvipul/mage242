<?php

namespace LR\QuickQuote\Controller\Index;

class LoadMyListProduct extends \Magento\Framework\App\Action\Action
{
    
    protected $_customerProductListItem;

    protected $_productCollection;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Appseconnect\B2BMage\Model\CustomerProductListItemFactory $customerProductListItem,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollection
    ) {
        parent::__construct($context);
        $this->_customerProductListItem = $customerProductListItem;
        $this->_productCollection = $productCollection;
    }


    public function execute()
    {
        $result['status'] = false;
        try {
            if ($listId = $this->getRequest()->getParam('list_id')) {
                $listCollection = $this->_customerProductListItem->create()->getCollection();
                $tableFrom = $listCollection->getSelect()->getPart('from');

                $collection = $this->_productCollection->create()->addAttributeToSelect('sku');
                $collection->getSelect()
                    ->joinLeft(
                        array('list' => $tableFrom['main_table']['tableName']),
                        "e.entity_id=list.product_id",
                        array('list.product_sku')
                    )->where('list.list_id = ' . $listId);

                if (count($collection) > 0) {
                    $result['products'] = $collection->toArray();
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
