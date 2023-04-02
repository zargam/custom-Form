<?php
namespace InternationalEnquires\Form\Helper;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\App\Request\DataPersistorInterface;

class Data extends AbstractHelper
{
    /**
     * @type ObjectManagerInterface
     */
    protected $objectManager;
	
	
	private $postData = null;
	
	 private $dataPersistor;

    /**
     * @param \Magento\Framework\App\Helper\Context $context
     */
    public function __construct(
        Context $context,
        ObjectManagerInterface $objectManager
    ) {
        $this->objectManager = $objectManager;
        parent::__construct($context);
    }

    /**
     * @param $ver
     * @param string $operator
     * @return mixed
     */
    public function versionCompare($ver, $operator = '>=')
    {
        $productMetadata = $this->objectManager->get(ProductMetadataInterface::class);
        $version = $productMetadata->getVersion();
        return version_compare($version, $ver, $operator);
    }
	 public function getPostValue($key)
    {
        if (null === $this->postData) {
            $this->postData = (array) $this->getDataPersistor()->get('intenq_form');
            $this->getDataPersistor()->clear('intenq_form');
        }

        if (isset($this->postData[$key])) {
            return (string) $this->postData[$key];
        }

        return '';
    }
	private function getDataPersistor()
    {
        if ($this->dataPersistor === null) {
            $this->dataPersistor = ObjectManager::getInstance()
                ->get(DataPersistorInterface::class);
        }

        return $this->dataPersistor;
    }
}
