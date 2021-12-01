<?php

namespace LR\QuickQuote\Model\ResourceModel\Quotes;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection  extends AbstractCollection
{
    public function _construct()
    {
        $this->_init('LR\QuickQuote\Model\Quotes', 'LR\QuickQuote\Model\ResourceModel\Quotes');
    }
}
