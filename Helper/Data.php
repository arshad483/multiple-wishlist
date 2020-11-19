<?php
/**
 * @category  BKozlic
 * @package   BKozlic\MultipleWishlist
 * @author    Berin Kozlic - berin.kozlic@gmail.com
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace BKozlic\MultipleWishlist\Helper;

use BKozlic\MultipleWishlist\Api\Data\MultipleWishlistInterface;
use BKozlic\MultipleWishlist\Api\Data\MultipleWishlistItemInterface;
use BKozlic\MultipleWishlist\Api\MultipleWishlistItemRepositoryInterface;
use BKozlic\MultipleWishlist\Api\MultipleWishlistRepositoryInterface;
use Exception;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Store\Model\ScopeInterface;
use Magento\Wishlist\Model\ItemFactory;
use Magento\Wishlist\Model\ResourceModel\Item;
use Psr\Log\LoggerInterface;

/**
 * Module's helper class
 */
class Data extends AbstractHelper
{
    /**
     * Helper constants
     */
    const XML_PATH_ENABLED = 'wishlist/multiple_wishlist_general/enabled';
    const XML_PATH_STRATEGY = 'wishlist/multiple_wishlist_general/wishlist_strategy';
    const XML_PATH_LIMIT = 'wishlist/multiple_wishlist_general/wishlist_limit';
    const DEFAULT_LIMIT = 100;
    const MAX_LIMIT = 1000;

    /**
     * @var MultipleWishlistItemRepositoryInterface
     */
    protected $itemRepository;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var Item
     */
    protected $mainItemResource;

    /**
     * @var ItemFactory
     */
    protected $mainItemFactory;

    /**
     * @var MultipleWishlistRepositoryInterface
     */
    protected $multipleWishlistRepository;

    /**
     * Data constructor.
     * @param Context $context
     * @param MultipleWishlistItemRepositoryInterface $itemRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param Item $mainItemResource
     * @param ItemFactory $mainItemFactory
     * @param MultipleWishlistRepositoryInterface $multipleWishlistRepository
     * @param LoggerInterface $logger
     */
    public function __construct(
        Context $context,
        MultipleWishlistItemRepositoryInterface $itemRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        Item $mainItemResource,
        ItemFactory $mainItemFactory,
        MultipleWishlistRepositoryInterface $multipleWishlistRepository,
        LoggerInterface $logger
    ) {
        parent::__construct($context);
        $this->itemRepository = $itemRepository;
        $this->logger = $logger;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->mainItemResource = $mainItemResource;
        $this->mainItemFactory = $mainItemFactory;
        $this->multipleWishlistRepository = $multipleWishlistRepository;
    }

    /**
     * Checks if module is enabled
     *
     * @return bool
     */
    public function isEnabled()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Checks if wishlist modal can be shown or not
     *
     * @return bool
     */
    public function canShowModal()
    {
        return $this->scopeConfig->getValue(self::XML_PATH_STRATEGY, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Returns wishlist limit number
     *
     * @return int
     */
    public function getWishlistLimit()
    {
        //@TODO Check if value is string, float, empty, negative.
        $limit = $this->scopeConfig->getValue(self::XML_PATH_LIMIT, ScopeInterface::SCOPE_STORE);
        if (!is_numeric($limit) || $limit < 0) {
            return self::DEFAULT_LIMIT;
        }

        if ($limit > self::MAX_LIMIT) {
            return self::MAX_LIMIT;
        }

        return (int)$limit;
    }

    /**
     * Returns filtered multiple wishlist item collection
     *
     * @param false|null|int $wishlist
     * @param null|int $itemId
     * @return array
     */
    public function getMultipleWishlistItems($wishlist = false, $itemId = null)
    {
        if ($wishlist !== false) {
            $this->searchCriteriaBuilder->addFilter(
                MultipleWishlistItemInterface::MULTIPLE_WISHLIST_ID,
                $wishlist,
                $wishlist ? 'eq' : 'null'
            );
        }

        if ($itemId) {
            $this->searchCriteriaBuilder->addFilter(
                MultipleWishlistItemInterface::MULTIPLE_WISHLIST_ITEM,
                $itemId
            );
        }

        $itemList = $this->itemRepository->getList($this->searchCriteriaBuilder->create())->getItems();

        return $this->makeUniqueCollection($itemList);
    }

    /**
     * Returns first multiple wishlist for a given wishlist id
     *
     * @param int $wishlistId
     * @return MultipleWishlistInterface
     */
    public function getFirstMultipleWishlist($wishlistId)
    {
        $this->searchCriteriaBuilder->addFilter(
            MultipleWishlistInterface::WISHLIST_ID,
            $wishlistId
        );

        $multipleWishlistList = $this->multipleWishlistRepository->getList(
            $this->searchCriteriaBuilder->create()
        )->getItems();

        return array_shift($multipleWishlistList);
    }

    /**
     * Recalculate qty for main wishlist item
     * @param int $itemId
     * @return void
     */
    public function recalculate($itemId)
    {
        $mainItemModel = $this->mainItemFactory->create();
        $this->mainItemResource->load($mainItemModel, $itemId);
        $items = $this->getMultipleWishlistItems(false, $itemId);

        if (!count($items)) {
            try {
                $this->mainItemResource->delete($mainItemModel);
            } catch (Exception $e) {
                $this->logger->error($e->getMessage());
            }
        }

        $qty = 0;
        foreach ($items as $item) {
            $qty += $item->getQty();
        }

        if ($mainItemModel->getId()) {
            $mainItemModel->setQty($qty);
            try {
                $this->mainItemResource->save($mainItemModel);
            } catch (AlreadyExistsException $e) {
                $this->logger->error($e->getMessage());
            } catch (Exception $e) {
                $this->logger->error($e->getMessage());
            }
        }
    }

    /**
     * Makes unique multiple wishlist item collection if there are duplicates
     *
     * @param $collection
     * @return array
     */
    public function makeUniqueCollection($collection)
    {
        $items = [];
        /** @var MultipleWishlistItemInterface $multipleWishlistItem */
        foreach ($collection as $multipleWishlistItem) {
            $multipleWishlist = $multipleWishlistItem->getMultipleWishlistId() ?: '0';
            $key = $multipleWishlistItem->getWishlistItemId() . $multipleWishlist;

            if (!in_array($key, array_keys($items))) {
                $items[$key] = $multipleWishlistItem;
            } else {
                $existing = $items[$key];

                try {
                    $this->itemRepository->delete($multipleWishlistItem);
                    $existing->setQty($existing->getQty() + $multipleWishlistItem->getQty());
                    $items[$key] = $existing->setData('changed', true);
                } catch (CouldNotDeleteException $e) {
                    $this->logger->error($e->getMessage());
                }
            }
        }

        foreach ($items as $key => $item) {
            if ($item->getData('changed')) {
                try {
                    $this->itemRepository->save($item);
                } catch (CouldNotSaveException $e) {
                    $this->logger->error($e->getMessage());
                }
            }
        }

        return array_values($items);
    }
}
