<?php
/**
 * @category  BKozlic
 * @package   BKozlic\MultipleWishlist
 * @author    Berin Kozlic - berin.kozlic@gmail.com
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace BKozlic\MultipleWishlist\Plugin\Controller\Wishlist;

use BKozlic\MultipleWishlist\Api\Data\MultipleWishlistInterface;
use BKozlic\MultipleWishlist\Api\MultipleWishlistRepositoryInterface;
use BKozlic\MultipleWishlist\Helper\Data;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\Page;
use Magento\Wishlist\Controller\Index\Index as MagentoWishlistController;
use Magento\Wishlist\Controller\WishlistProviderInterface;
use Psr\Log\LoggerInterface;

/**
 * Plugin class for changing page title on wishlist list page
 */
class Index
{
    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var Data
     */
    protected $moduleHelper;

    /**
     * @var MultipleWishlistRepositoryInterface
     */
    protected $multipleWishlistRepository;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var WishlistProviderInterface
     */
    protected $wishlistProvider;

    /**
     * Wishlist Index Controller Plugin constructor.
     *
     * @param RequestInterface $request
     * @param MultipleWishlistRepositoryInterface $multipleWishlistRepository
     * @param Data $moduleHelper
     * @param LoggerInterface $logger
     * @param WishlistProviderInterface $wishlistProvider
     */
    public function __construct(
        RequestInterface $request,
        MultipleWishlistRepositoryInterface $multipleWishlistRepository,
        Data $moduleHelper,
        LoggerInterface $logger,
        WishlistProviderInterface $wishlistProvider
    ) {
        $this->request = $request;
        $this->moduleHelper = $moduleHelper;
        $this->multipleWishlistRepository = $multipleWishlistRepository;
        $this->logger = $logger;
        $this->wishlistProvider = $wishlistProvider;
    }

    /**
     * Change wishlist page title
     *
     * @param MagentoWishlistController $subject
     * @param Page $result
     * @return Page
     */
    public function afterExecute(MagentoWishlistController $subject, Page $result)
    {
        if (!$this->moduleHelper->isEnabled()) {
            return $result;
        }

        $multipleWishlistId = $this->request->getParam(MultipleWishlistInterface::MULTIPLE_WISHLIST_PARAM_NAME);

        if ($multipleWishlistId) {
            try {
                $wishlist = $this->multipleWishlistRepository->get($multipleWishlistId);
                $result->getConfig()->getTitle()->set($wishlist->getName());
            } catch (NoSuchEntityException $e) {
                $this->logger->error($e->getMessage());
            }
        }

        if (!$multipleWishlistId) {
            $wishlists = $this->moduleHelper->getAllMultipleWishlists(
                $this->wishlistProvider->getWishlist()->getId()
            );
            $firstWishlist = array_shift($wishlists);
            $items = $this->moduleHelper->getMultipleWishlistItems(null);

            if (!count($items) && $firstWishlist) {
                $result->getConfig()->getTitle()->set($firstWishlist->getName());
                $this->request->setParams(array_merge(
                    $this->request->getParams(),
                    [MultipleWishlistInterface::MULTIPLE_WISHLIST_PARAM_NAME => $firstWishlist->getId()]
                ));
            } else {
                $result->getConfig()->getTitle()->set(__('Default Wishlist'));
            }
        }

        return $result;
    }
}
