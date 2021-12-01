<?php

namespace LR\QuickQuote\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Quotes extends AbstractDb
{
    public function _construct()
    {
        $this->_init('lr_quote', 'id');
    }
}
