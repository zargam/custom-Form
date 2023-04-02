<?php
namespace InternationalEnquires\Form\Controller\Post;
use Magento\Contact\Model\ConfigInterface;
use Magento\Contact\Model\MailInterface;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use InternationalEnquires\Form\Rewrite\Magento\Framework\Mail\Template\TransportBuilder;

/**
 * Post controller class
 */
class Index extends \Magento\Contact\Controller\Index\Post
{
    const FOLDER_LOCATION = 'contactattachment';

    /**
     * @var DataPersistorInterface
     */
    private $dataPersistor;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var MailInterface
     */
    private $mail;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var UploaderFactory
     */
    private $fileUploaderFactory;

    /**
     * @var Filesystem
     */
    private $fileSystem;

    /**
     * @var StateInterface
     */
    private $inlineTranslation;

    /**
     * @var ConfigInterface
     */
    private $contactsConfig;

    /**
     * @var TransportBuilder
     */
    private $transportBuilder;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $file;

    /**
     * @var \Magento\Framework\Filesystem\Io\File
     */
    protected $scopeConfig;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Contact\Model\MailInterface $mail
     * @param \Magento\Framework\App\Request\DataPersistorInterface $dataPersistor
     * @param \Psr\Log\LoggerInterface|null $logger
     * @param \Magento\MediaStorage\Model\File\UploaderFactory $fileUploaderFactory
     * @param \Magento\Framework\Filesystem $fileSystem
     * @param \Magento\Framework\Translate\Inline\StateInterface $inlineTranslation
     * @param \Magento\Contact\Model\ConfigInterface $contactsConfig
     * @param \ReturnRequest\Form\Rewrite\Magento\Framework\Mail\Template\TransportBuilder $transportBuilder
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Framework\Filesystem\Io\File $file
     */
    public function __construct(
        Context $context,
        MailInterface $mail,
        DataPersistorInterface $dataPersistor,
        LoggerInterface $logger = null,
        UploaderFactory $fileUploaderFactory,
        Filesystem $fileSystem,
        StateInterface $inlineTranslation,
        ConfigInterface $contactsConfig,
        TransportBuilder $transportBuilder,
        StoreManagerInterface $storeManager,
        ScopeConfigInterface $scopeConfig,
        File $file
    ) {
        $this->context = $context;
        $this->mail = $mail;
        $this->dataPersistor = $dataPersistor;
        $this->logger = $logger ?: ObjectManager::getInstance()->get(LoggerInterface::class);
        $this->fileUploaderFactory = $fileUploaderFactory;
        $this->fileSystem = $fileSystem;
        $this->inlineTranslation = $inlineTranslation;
        $this->contactsConfig = $contactsConfig;
        $this->transportBuilder = $transportBuilder;
        $this->storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->file = $file;
        parent::__construct($context, $contactsConfig, $mail, $dataPersistor, $logger);
    }

    /**
     * Post user question
     * @return Redirect
     */
    public function execute()
    {
        if (!$this->getRequest()->isPost()) {
            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }
        try {
            $this->sendEmail($this->validatedParams());
            $this->messageManager->addSuccessMessage(
                __('Thanks for contacting us with your comments and questions. We\'ll respond to you very soon.')
            );
            $this->dataPersistor->clear('intenq_form');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->dataPersistor->set('intenq_form', $this->getRequest()->getParams());
       
		} catch (\Exception $e) {
            $this->logger->critical($e);
            $this->messageManager->addErrorMessage(
                __('An error occurred while processing your form. Please try again later.')
            );
            $this->dataPersistor->set('intenq_form', $this->getRequest()->getParams());
        }
        return $this->resultRedirectFactory->create()->setPath('international-enquiries');
    }

    /**
     * @param array $post Post data from contact form
     * @return void
     */
    private function sendEmail($post)
    {
        $this->send(
            $post['email'],
            ['data' => new DataObject($post)]
        );
    }

    /**
     * Send email from contact form
     * @param string $replyTo
     * @param array $variables
     * @return void
     */
    public function send($replyTo, array $variables)
    {
		
        $filePath = null;
        $fileName = null;
        $uploaded = false;

        try {
            $fileCheck = $this->fileUploaderFactory->create(['fileId' => 'company_legal_certification_img']);
            $file = $fileCheck->validateFile();
            $attachment = $file['name'] ?? null;
        } catch (\Exception $e) {
            $attachment = null;
        }

        if ($attachment) {
            $upload = $this->fileUploaderFactory->create(['fileId' => 'company_legal_certification_img']);
            $upload->setAllowRenameFiles(true);
            $upload->setFilesDispersion(true);
            $upload->setAllowCreateFolders(true);
            $upload->setAllowedExtensions(['txt', 'csv', 'jpg', 'jpeg', 'gif', 'png', 'pdf', 'doc', 'docx']);

            $path = $this->fileSystem
                ->getDirectoryRead(DirectoryList::MEDIA)
                ->getAbsolutePath(self::FOLDER_LOCATION);
            $result = $upload->save($path);
            $uploaded = self::FOLDER_LOCATION . $upload->getUploadedFilename();
            $filePath = $result['path'] . $result['file'];
            $fileName = $result['name'];
        }

        /** @see \Magento\Contact\Controller\Index\Post::validatedParams() */
        $replyToName = !empty($variables['data']['name']) ? $variables['data']['name'] : null;

        $this->inlineTranslation->suspend();

        $storeScope = \Magento\Store\Model\ScopeInterface::SCOPE_STORE;
        $transport = $this->transportBuilder
            ->setTemplateIdentifier("intEnq_email_attachment_email_template")
            ->setTemplateOptions(
                [
                    'area' => Area::AREA_FRONTEND,
                    'store' => $this->storeManager->getStore()->getId(),
                ]
            )
            ->setTemplateVars($variables)
            ->setFrom($this->contactsConfig->emailSender())
            ->addTo($this->contactsConfig->emailRecipient())
            ->setReplyTo($replyTo, $replyToName)
            ->getTransport();

        if ($uploaded && !empty($filePath) && $this->file->fileExists($filePath)) {
            $mimeType = mime_content_type($filePath);

            $transport = $this->transportBuilder
                ->setTemplateIdentifier("intEnq_email_attachment_email_template")
                ->setTemplateOptions(
                    [
                        'area' => Area::AREA_FRONTEND,
                        'store' => $this->storeManager->getStore()->getId(),
                    ]
                )
                ->addAttachment($this->file->read($filePath), $fileName, $mimeType)
                ->setTemplateVars($variables)
                ->setFrom($this->contactsConfig->emailSender())
                ->addTo($this->contactsConfig->emailRecipient())
                ->setReplyTo($replyTo, $replyToName)
                ->getTransport();
        }

        $transport->sendMessage();
        $this->inlineTranslation->resume();
    }

    /**
     * @return array
     * @throws \Exception
     */
    private function validatedParams()
    {
        $request = $this->getRequest();
        if (trim($request->getParam('first_name')) === '') {
            throw new LocalizedException(__('First Name is missing'));
        }
		 if (trim($request->getParam('last_name')) === '') {
            throw new LocalizedException(__('Last Name is missing'));
        }
		if (false === \strpos($request->getParam('email'), '@')) {
            throw new LocalizedException(__('Invalid email address'));
        }
		 if (trim($request->getParam('phone_no')) === '') {
            throw new LocalizedException(__('phone_no is missing'));
        }
		if (trim($request->getParam('company_name')) === '') {
            throw new LocalizedException(__('company name is missing'));
        }
		if (trim($request->getParam('country')) === '') {
            throw new LocalizedException(__('country name is missing'));
        }
		if (trim($request->getParam('company_legal_certification_name')) === '') {
            throw new LocalizedException(__('company legal certification name name is missing'));
        }
		
		
		/*
        if (trim($request->getParam('reason')) === '') {
            throw new LocalizedException(__('reason is missing'));
        }
        if (false === \strpos($request->getParam('email'), '@')) {
            throw new LocalizedException(__('Invalid email address'));
        }
        if (trim($request->getParam('hideit')) !== '') {
            throw new LocalizedException(__('Error'));
        }
		
*/
        return $request->getParams();
    }
}
