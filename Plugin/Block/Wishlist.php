<?php
/**
 * @category  BKozlic
 * @package   BKozlic\MultipleWishlist
 * @author    Berin Kozlic - berin.kozlic@gmail.com
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace BKozlic\MultipleWishlist\Plugin\Block;

use BKozlic\MultipleWishlist\Api\Data\MultipleWishlistItemInterface;
use BKozlic\MultipleWishlist\Api\MultipleWishlistItemRepositoryInterface;
use BKozlic\MultipleWishlist\Block\MultipleWishlistSwitcher;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\RequestInterface;
use Magento\Wishlist\Block\Customer\Wishlist as MagentoWishlistBlock;
use Magento\Wishlist\Model\ResourceModel\Item\Collection;

/**
 * Plugin class for filtering wishlist collection by current multiple wishlist id
 */
class Wishlist
{
    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var MultipleWishlistItemRepositoryInterface
     */
    protected $itemRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * Wishlist Block Plugin constructor.
     *
     * @param RequestInterface $request
     * @param MultipleWishlistItemRepositoryInterface $itemRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        RequestInterface $request,
        MultipleWishlistItemRepositoryInterface $itemRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->request = $request;
        $this->itemRepository = $itemRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * Process filtering collection by custom multiple wishlist id
     *
     * @param MagentoWishlistBlock $subject
     * @param Collection $result
     * @return Collection
     */
    public function afterGetWishlistItems(MagentoWishlistBlock $subject, Collection $result)
    {
        //@TODO If module is enabled
        //@TODO Update page title
        $multipleWishlistId = $this->request->getParam(MultipleWishlistSwitcher::MULTIPLE_WISHLIST_PARAM_NAME);
        $idToQtyMapper = $this->getMultipleWishlistItemIdToQtyMapper($multipleWishlistId);

        $result->addFieldToFilter('wishlist_item_id', ['in' => array_keys($idToQtyMapper)]);

        foreach ($result as $wishlistItem) {
            if (isset($idToQtyMapper[$wishlistItem->getId()])) {
                //@TODO Check if there are duplicates. If yes sum the qty
                $wishlistItem->setQty($idToQtyMapper[$wishlistItem->getId()]);
            }
        }

        return $result;
    }

    /**
     * Returns ids of items in the specified multiple wishlist
     *
     * @param int|null $multipleWishlistId
     * @return array
     */
    protected function getMultipleWishlistItemIdToQtyMapper($multipleWishlistId)
    {
        //@TODO If there are no items to the default wishlist use first one from the list
        $this->searchCriteriaBuilder->addFilter(
            MultipleWishlistItemInterface::MULTIPLE_WISHLIST_ID,
            $multipleWishlistId,
            $multipleWishlistId ? 'eq' : 'null'
        );

        $itemList = $this->itemRepository->getList($this->searchCriteriaBuilder->create())->getItems();
        $ids = [];
        foreach ($itemList as $item) {
            $ids[$item->getWishlistItemId()] = $item->getQty();
        }

        return $ids;
    }
}