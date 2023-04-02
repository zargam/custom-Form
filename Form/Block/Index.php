<?php
namespace InternationalEnquires\Form\Block;

class Index extends \Magento\Framework\View\Element\Template
{
	protected $productCollectionFactory;
	public function __construct(
	\Magento\Framework\View\Element\Template\Context $context
	)
	{
        parent::__construct($context);
	}

}