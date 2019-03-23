<?php

namespace OssImportProductStream\Subscriber;

use Enlight\Event\SubscriberInterface;
use OssImportProductStream\OssImportProductStream;
use OssImportProductStream\Components\SwagImportExport\DbAdapters\OssProductStreamDbAdapter;
use OssImportProductStream\Components\SwagImportExport\Service\ImportService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class SwagImportExport
 * @package OssImportProductStream\Subscriber
 * @author  Odessite <alexey.palamar@odessite.com.ua>
 */
class SwagImportExport implements SubscriberInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public static function getSubscribedEvents()
    {
        return [
            // Profile handling
            'Shopware_Components_SwagImportExport_Factories_CreateDbAdapter' => 'onCreateDbAdapter',
            // Import data manipulation
            'Enlight_Bootstrap_AfterInitResource_swag_import_export.import_service' => ['decorateImportService',1],
        ];
    }

    /**
     * @param \Enlight_Event_EventArgs $args
     * @return null|OssProductStreamDbAdapter
     */
    public function onCreateDbAdapter(\Enlight_Event_EventArgs $args)
    {
        if ($args->adapterType == OssImportProductStream::ADAPTER_TYPE) {
            return new OssProductStreamDbAdapter();
        }

        return null;
    }

    /**
     * decorates Import Service
     */
    public function decorateImportService()
    {
        $this->container->set(
            'swag_import_export.import_service',
            new ImportService(
                $this->container->get('swag_import_export.import_service')
            )
        );
    }
}
