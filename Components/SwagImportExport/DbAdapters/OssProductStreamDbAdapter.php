<?php

namespace OssImportProductStream\Components\SwagImportExport\DbAdapters;

use Doctrine\ORM\AbstractQuery;
use Shopware\Components\Model\ModelManager;
use Shopware\Components\SwagImportExport\DbAdapters\DataDbAdapter;
use Shopware\Models\ProductStream\ProductStream;
use Shopware\Models\Attribute\ProductStream as ProductStreamAttribute;
use Shopware\Bundle\SearchBundleDBAL\QueryBuilder;

/**
 * Class OssProductStreamDbAdapter
 * @package OssImportProductStream\Components\SwagImportExport\DbAdapters
 * @author  Odessite <alexey.palamar@odessite.com.ua>
 */
class OssProductStreamDbAdapter implements DataDbAdapter
{
    /**
     * @var ModelManager
     */
    protected $modelManager;

    /**
     * @var array
     */
    protected $unprocessedData;

    /**
     * @var array
     */
    protected $logMessages;

    /**
     * @var string
     */
    protected $logState;

    public function __construct()
    {
        $this->modelManager = Shopware()->Container()->get('models');
    }

    /**
     * @return array
     */
    public function getDefaultColumns()
    {
        return [
            'variant.number as orderNumber',
        ];
    }

    /**
     * @param array $ids
     * @param array $columns
     *
     * @throws \Exception
     *
     * @return array
     */
    public function read($ids, $columns)
    {
        if (empty($ids)) {
            $message = Shopware()->Snippets()->getNamespace('backend/main')
                ->get('adapters/articles_no_ids', 'Can not read articles without ids.');
            throw new \Exception($message);
        }

        $builder = $this->getBuilder($columns, $ids);

        $query = $builder->getQuery();
        $query->setHydrationMode(AbstractQuery::HYDRATE_ARRAY);

        $paginator = $this->modelManager->createPaginator($query);

        $result['default'] = $paginator->getIterator()->getArrayCopy();

        return $result;
    }

    /**
     * @return array
     */
    public function getUnprocessedData()
    {
        return $this->unprocessedData;
    }

    /**
     * @param $start
     * @param $limit
     * @param $filter
     *
     * @throws \Exception
     *
     * @return array
     */
    public function readRecordIds($start, $limit, $filter)
    {
        $query = $this->modelManager->getConnection()->createQueryBuilder();
        $query->select([
            'articles.article_id as id',
        ]);
        $query->from('s_product_streams', 'streams')
            ->innerJoin('streams', 's_product_streams_attributes', 'streamAttribute', 'streamAttribute.streamID = streams.id')
            ->innerJoin('streams', 's_product_streams_selection', 'articles', 'articles.stream_id = streams.id')
            ->where('streamAttribute.imported=1')
            ->groupBy('articles.article_id');

        if ($start) {
            $query->setFirstResult($start);
        }

        if ($limit) {
            $query->setMaxResults($limit);
        }

        $records = $query->execute()->fetchAll();
        $result = [];
        if ($records) {
            foreach ($records as $value) {
                $result[] = $value['id'];
            }
        }

        return $result;
    }

    /**
     * @param $records
     *
     * @throws \Enlight_Event_Exception
     * @throws \Exception
     */
    public function write($records)
    {
        if (empty($records['default'])) {
            $message = Shopware()->Snippets()->getNamespace('backend/main')
                ->get('adapters/articles_number_required', 'Aricle order number is required');
            throw new \Exception($message);
        }
        if (empty(Shopware()->Container()->get('swag_import_export.import_service')->getFileName())) {
            $message = Shopware()->Snippets()->getNamespace('backend/main')
                ->get('adapters/product_stream_undefined', 'Product stream name is undefined');
            throw new \Exception($message);
        }

        $productStream =  $this->modelManager->getRepository(
            ProductStream::class
        )->findOneBy(
            ['name' => Shopware()->Container()->get('swag_import_export.import_service')->getFileName()]
        );

        if (!($productStream instanceof ProductStream)) {
            $productStream = new ProductStream();
            $productStream->setName(Shopware()->Container()->get('swag_import_export.import_service')->getFileName());
            $productStream->setType(2);
            $productStream->setConditions(null);
        }
        if (!($productStream->getAttribute() instanceof ProductStreamAttribute)) {
            $productStreamAttribute = new ProductStreamAttribute();
        } else {
            $productStreamAttribute = $productStream->getAttribute();
        }
        $productStreamAttribute->setImported(1);

        $productStream->setAttribute($productStreamAttribute);
        $productStream->setDescription(
            '`'.Shopware()->Container()->get('swag_import_export.import_service')->getFileName().'`'.
            Shopware()->Config()->getByNamespace('OssImportProductStream', 'description')
        );

        $this->modelManager->persist($productStream);
        $this->modelManager->flush($productStream);
        $this->modelManager->refresh($productStream);

        if (!intval($productStream->getId())) {
            $message = Shopware()->Snippets()->getNamespace('backend/main')
                ->get('adapters/creation_failed', 'Unexpected error occurred while creation process');
            throw new \Exception($message);
        }

//        TODO: I'm not sure should we delete or we should combine
//        Shopware()->Container()->get('dbal_connection')->executeUpdate(
//            'DELETE FROM s_product_streams_selection WHERE stream_id = :streamId',
//            [':streamId' => $productStream->getId()]
//        );

        Shopware()->Container()->get('dbal_connection')->executeUpdate(
            'INSERT IGNORE INTO s_product_streams_selection (stream_id, article_id) 
            SELECT '.$productStream->getId().', articleID 
            FROM s_articles_details 
            WHERE ordernumber IN (:orderNumberIds)',
            [':orderNumberIds' => array_column($records['default'], 'orderNumber')],
            [':orderNumberIds' =>  \Doctrine\DBAL\Connection::PARAM_INT_ARRAY]
        );
    }

    /**
     * @return array
     */
    public function getSections()
    {
        return [
            [
                'id' => 'default',
                'name' => 'default ',
            ],
        ];
    }

    /**
     * @param string $section
     *
     * @return bool|mixed
     */
    public function getColumns($section)
    {
        $method = 'get' . ucfirst($section) . 'Columns';

        if (method_exists($this, $method)) {
            return $this->{$method}();
        }

        return false;
    }

    /**
     * @param $message
     *
     * @throws \Exception
     */
    public function saveMessage($message)
    {
        $errorMode = Shopware()->Config()->get('SwagImportExportErrorMode');

        if ($errorMode === false) {
            throw new \Exception($message);
        }

        $this->setLogMessages($message);
        $this->setLogState('true');
    }

    /**
     * @return array
     */
    public function getLogMessages()
    {
        return $this->logMessages;
    }

    /**
     * @param $logMessages
     */
    public function setLogMessages($logMessages)
    {
        $this->logMessages[] = $logMessages;
    }

    /**
     * @return string
     */
    public function getLogState()
    {
        return $this->logState;
    }

    /**
     * @param $logState
     */
    public function setLogState($logState)
    {
        $this->logState = $logState;
    }

    /**
     * @param $columns
     * @param $ids
     *
     * @return QueryBuilder
     */
    public function getBuilder($columns, $ids)
    {
        $builder = $this->modelManager->createQueryBuilder();
        $builder->select($columns)
            ->from('Shopware\Models\Article\Article', 'article')
            ->innerJoin('article.mainDetail', 'variant')
            ->where('article.id IN (:ids)')
            ->setParameters([
                'ids' => $ids
            ]);

        return $builder;
    }
}
