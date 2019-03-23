<?php

namespace OssImportProductStream;

use Shopware\Components\Plugin\Context\InstallContext;
use Shopware\Components\Plugin\Context\UninstallContext;
use Shopware\Components\Plugin;
use Shopware\Components\Plugin\Context\ActivateContext;
use Shopware\Components\Plugin\Context\DeactivateContext;
use Shopware\CustomModels\ImportExport\Profile;

/**
 * Class OssImportProductStream
 * @package OssImportProductStream
 * @author  Odessite <alexey.palamar@odessite.com.ua>
 */
class OssImportProductStream extends Plugin
{
    const PROFILE_NAME = 'oss_product_stream';
    const ADAPTER_TYPE = 'OssProductStream';

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            'Enlight_Controller_Front_StartDispatch' => 'onEnlightControllerFrontStartDispatch',
        );
    }

    /**
     * This method can be overridden
     *
     * @param InstallContext $installContext
     */
    public function install(InstallContext $installContext)
    {
        parent::install($installContext);
        $this->installProfile()
        ->updateSchema();
    }
    /**
     * This method can be overridden
     *
     * @param UninstallContext $context
     */
    public function uninstall(UninstallContext $context)
    {
        parent::uninstall($context);
        $this->uninstallProfile()
        ->dropSchema();
    }

    /**
     * This method can be overridden
     *
     * @param ActivateContext $context
     */
    public function activate(ActivateContext $context)
    {
        $context->scheduleClearCache(InstallContext::CACHE_LIST_DEFAULT);
    }

    /**
     * This method can be overridden
     *
     * @param DeactivateContext $context
     */
    public function deactivate(DeactivateContext $context)
    {
        $context->scheduleClearCache(InstallContext::CACHE_LIST_DEFAULT);
    }

    public function onEnlightControllerFrontStartDispatch()
    {
        $this->container->get('loader')->registerNamespace('OssImportProductStream\Components', $this->getPath() . '/Components/');
    }

    /**
     * Creates import/export profile
     * TODO: profile will be unexisted if someone delete SwagImportExport and then install again, so in general probably would be better to extend profile repo and check is profile already exit
     *
     * @throws \Exception
     * @return OssImportProductStream
     */
    public function installProfile()
    {
//        Required to call import/export for profile repo will be available
        $this->container->get('swag_import_export.profile_service');
        $repository = $this->container->get('models')->getRepository(Profile::class);
        /** @var \Shopware\CustomModels\ImportExport\Repository $repository */
        $profile = $repository->findOneBy([
            'name' => self::PROFILE_NAME,
        ]);

        if (!($profile instanceof Profile)) {
            $profile = new Profile();
            $profile->setName(self::PROFILE_NAME);
            $profile->setDescription(
                self::PROFILE_NAME.'_description'
            );
            $profile->setType('OssProductStream');
            $profile->setDefault(1);
            $treePath = $this->getPath() . '/Components/SwagImportExport/Profiles/oss_product_stream.json';
            if (file_exists($treePath)) {
                $tree = file_get_contents($treePath);
                $tree = preg_replace('/\\s+/', '', $tree);
                $profile->setTree($tree);
            }

            $this->container->get('models')->persist($profile);
        }

        return $this;
    }

    /**
     * Remove import/export profile
     *
     * @throws \Exception
     * @return OssImportProductStream
     */
    public function uninstallProfile()
    {
        $this->container->get('swag_import_export.profile_service');
        $repository = $this->container->get('models')->getRepository(Profile::class);
        /** @var \Shopware\CustomModels\ImportExport\Repository $repository */
        $profile = $repository->findOneBy([
            'name' => self::PROFILE_NAME,
        ]);

        if ($profile instanceof Profile) {
            $this->container->get('models')->remove($profile);
        }

        return $this;
    }

    /**
     * add attribute for mark imported profile stream
     *
     * @return OssImportProductStream
     */
    private function updateSchema()
    {
        /**@var \Shopware\Bundle\AttributeBundle\Service\CrudService $service **/
        $service = $this->container->get('shopware_attribute.crud_service');

        $service->update('s_product_streams_attributes', 'imported', 'boolean');

        Shopware()->Models()->generateAttributeModels([
            's_product_streams_attributes'
        ]);

        return $this;
    }

    /**
     * remove attribute
     *
     * @return OssImportProductStream
     */
    private function dropSchema()
    {
        /**@var \Shopware\Bundle\AttributeBundle\Service\CrudService $service **/
        $service = $this->container->get('shopware_attribute.crud_service');

        try {
            $service->delete('s_product_streams_attributes', 'imported');
        } catch (\Exception $e) {
        }

        Shopware()->Models()->generateAttributeModels([
            's_product_streams_attributes'
        ]);

        return $this;
    }
}
