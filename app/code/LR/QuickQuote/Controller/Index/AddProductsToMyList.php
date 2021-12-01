<?php

namespace LR\QuickQuote\Controller\Index;

class AddProductsToMyList extends \Magento\Framework\App\Action\Action
{
    
    protected $_customerProductList;

    protected $_customerProductListItem;

    protected $_configurable;

    protected $_bundleSelection;

    protected $_productloader;

    protected $_priceRuleData;

    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Appseconnect\B2BMage\Model\CustomerProductListFactory $customerProductList,
        \Appseconnect\B2BMage\Model\CustomerProductListItemFactory $customerProductListItem,
        \Magento\ConfigurableProduct\Model\Product\Type\Configurable $configurable,
        \Magento\Bundle\Model\Selection $bundleSelection,
        \Magento\Catalog\Model\ProductFactory $productloader,
        \Appseconnect\B2BMage\Helper\PriceRule\Data $priceRuleData
    ) {
        parent::__construct($context);
        $this->_customerProductList = $customerProductList;
        $this->_customerProductListItem = $customerProductListItem;
        $this->_configurable = $configurable;
        $this->_bundleSelection = $bundleSelection;
        $this->_productloader = $productloader;
        $this->_priceRuleData = $priceRuleData;
    }

    public function execute()
    {
        $result['status'] = false;
        $postData = $this->getRequest()->getParams();
        if (isset($postData['list_id']) && $postData['list_id'] > 1) {
            if (isset($postData['items']) && !empty($postData['items'])) {
                try {
                    $list = $this->_customerProductList->create()->load($postData['list_id']);
                    if ($list && $list->getId()) {
                        foreach ($postData['items'] as $item) {
                            $productId = $item['product'];
                            $product = $this->_productloader->create()->load($productId);
                            $requestData = [];
                            $requestData['list_id'] = $list->getId();
                            $requestData['qty'] = $item['qty'];
                            $requestData['product_type'] = $product->getTypeId();
                            $requestData['data_all'] = [];
                            $this->processProduct($list, $product, $requestData);
                        }
                        $result['message'] = __('This product was added to your quote list.');
                        $result['status'] = true;
                    } else {
                        $result['message'] = __('List id does not exist.');
                    }
                } catch (\Exception $ex) {
                    $result['message'] = $ex->getMessage();
                }
            } else {
                $result['message'] = __('Please add products to quick quote list.');
            }
        } else {
            $result['message'] = __('Please select list.');
        }

    	$this->getResponse()->representJson(
            $this->_objectManager->get(\Magento\Framework\Json\Helper\Data::class)->jsonEncode($result)
        );
    }

    protected function processProduct($list, $product, $postData)
    {
        $unitPrice = $this->_priceRuleData->getFinalProductPrice($product);
        if (!isset($postData['qty'])) {
            $postData['qty'] = 1;
        }
        $strOption = '';

        if (isset($postData['data_all']) && !empty($postData['data_all'])) {
            parse_str($postData['data_all'], $productData);
            foreach ($productData as $key => $addData) {
                if (in_array($key, array('related_product', 'form_key', 'qty'))) {
                    unset($productData[$key]);
                }
            }

            if ($postData['product_type'] == 'configurable') {
                $unitPrice = 0;
                $childProduct = $this->_configurable->getProductByAttributes($productData['super_attribute'], $product);
                $unitPrice += $this->_priceRuleData->getFinalProductPrice($childProduct);
            } else if ($postData['product_type'] == 'bundle') {
                $unitPrice = 0;
                foreach ($productData['bundle_option'] as $optionId => $selectionId) {
                    if ($selectionId) {
                        $bundleSlection = $this->_bundleSelection->load($selectionId);
                        $simpleProduct = $this->_productloader->create()->load($bundleSlection->getProductId());
                        $unitPrice += number_format($this->_priceRuleData->getFinalProductPrice($simpleProduct), 2);
                    }
                }
            } else if ($postData['product_type'] == 'grouped') {
                $unitPrice = 0;
                foreach ($productData['super_group'] as $productId => $simpleQty) {
                    if ($simpleQty > 0) {
                        $simpleProduct = $this->_productloader->create()->load($productId);
                        $unitPrice += number_format($this->_priceRuleData->getFinalProductPrice($simpleProduct), 2) * $simpleQty;
                        $this->createSimpleList($postData, $productId, $simpleQty, number_format($this->_priceRuleData->getFinalProductPrice($simpleProduct), 2), $list, $postData);
                    }
                }
            }

            $strOption = http_build_query($productData);
        }

        if ($postData['product_type'] != 'grouped') {
            $listItemCollection = $this->_customerProductListItem->create()->getCollection()
                ->addFieldToFilter('list_id', array('eq' => $postData['list_id']))
                ->addFieldToFilter('product_id', array('eq' => $postData['product_type']));

            if (isset($postData['product_type']) && $postData['product_type'] != 'simple') {
                $listItemCollection
                    ->addFieldToFilter('product_type', $postData['product_type'])
                    ->addFieldToFilter('product_option', $strOption);
            } else {
                $listItemCollection
                    ->addFieldToFilter('product_type', array('null' => true));
            }

            if ($listItemCollection->count() <= 0) {
                $listItem = $this->_customerProductListItem->create();
                $listItem
                    ->setProductId($postData['product_type'])
                    ->setProductSku($product->getSku())
                    ->setProductDescription($product->getName())
                    ->setProductUom($product->getProductUom())
                    ->setListId($postData['list_id'])
                    ->setQty($postData['qty'])
                    ->setUnitPrice($unitPrice)
                    ->setTotalPrice($unitPrice * $postData['qty']);


                if ($postData['product_type'] != 'simple') {
                    $listItem->setProductAddtocartData($postData['data_all']);
                    $listItem->setProductType($product->getTypeId());
                    $listItem->setProductOption($strOption);
                }

                $listItem->save();

                $list->setItem($list->getItem() + 1)
                    ->setTotalPrice($list->getTotalPrice() + ($unitPrice * $postData['qty']))
                    ->save();
            } else {
                $listItem = $listItemCollection->getFirstItem();
                $listItem
                    ->setTotalPrice(($listItem->getUnitPrice() * ($listItem->getQty() + $postData['qty'])))
                    ->setQty($listItem->getQty() + $postData['qty']);
                if ($postData['product_type'] != 'simple') {
                    $listItem->setProductAddtocartData($postData['data_all']);
                    $listItem->setProductType($product->getTypeId());
                    $listItem->setProductOption($strOption);
                }
                $listItem->save();

                $list->setTotalPrice($list->getTotalPrice() + ($listItem->getUnitPrice() * $postData['qty']))->save();
            }
        }
    }

    protected function createSimpleList($postData, $productId, $simpleQty, $unitPrice, $list, $allData)
    {
        $product = $this->_productloader->create()->load($productId);
        $listItemCollection = $this->_customerProductListItem->create()->getCollection()
            ->addFieldToFilter('list_id', array('eq' => $postData['list_id']))
            ->addFieldToFilter('product_id', array('eq' => $allData['product_id']));


        parse_str($allData['data_all'], $productData);
        $preProductData = $productData;
        foreach ($productData as $key => $addData) {
            if (in_array($key, array('related_product', 'form_key', 'qty'))) {
                unset($productData[$key]);
            }
        }
        $productData['super_group'] = array($productId => $simpleQty);
        $preProductData['super_group'] = array($productId => $simpleQty);
        $strOption = http_build_query($productData);

        if (isset($allData['product_type']) && $allData['product_type'] != 'simple') {
            $listItemCollection->addFieldToFilter('product_type', $product->getTypeId())
                ->addFieldToFilter('product_option', $strOption);
        }

        if ($listItemCollection->count() <= 0) {
            $listItem = $this->_customerProductListItem->create();
            $listItem->setProductId($allData['product_id'])
                ->setProductSku($product->getSku())
                ->setProductDescription($product->getName())
                ->setProductUom($product->getProductUom())
                ->setListId($postData['list_id'])
                ->setQty($simpleQty)
                ->setUnitPrice($unitPrice)
                ->setTotalPrice($unitPrice * $simpleQty);



            $preStrOption = http_build_query($preProductData);

            $listItem->setProductAddtocartData($preStrOption);
            $listItem->setProductType($allData['product_type']);
            $listItem->setProductOption($strOption);

            $listItem->save();

            $list->setItem($list->getItem() + 1)
                ->setTotalPrice($list->getTotalPrice() + ($unitPrice * $simpleQty))
                ->save();
        } else {
            $listItem = $listItemCollection->getFirstItem();
            $preProductData['super_group'] = array($productId => $listItem->getQty() + $simpleQty);
            $listItem
                ->setTotalPrice(($listItem->getUnitPrice() * ($listItem->getQty() + $simpleQty)))
                ->setQty($listItem->getQty() + $simpleQty);


            $preStrOption = http_build_query($preProductData);

            $listItem->setProductAddtocartData($preStrOption);
            $listItem->setProductType($allData['product_type']);
            $listItem->setProductOption($strOption);

            $listItem->save();

            $list->setTotalPrice($list->getTotalPrice() + ($listItem->getUnitPrice() * $simpleQty))
                ->save();
        }
    }
}