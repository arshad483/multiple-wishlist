<?php
/**
 * @category  BKozlic
 * @package   BKozlic\MultipleWishlist
 * @author    Berin Kozlic - berin.kozlic@gmail.com
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
namespace BKozlic\MultipleWishlist\Controller\Manage;

use BKozlic\MultipleWishlist\Controller\AbstractManage;
use BKozlic\MultipleWishlist\Model\MultipleWishlistRepository;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Data\Form\FormKey\Validator;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;

/**
 * Controller for multiple wishlist deletion
 */
class Delete extends AbstractManage implements HttpPostActionInterface
{
    /**
     * Process multiple wishlist removal
     */
    public function execute()
    {
        //@TODO Move items to another wishlist
        $params = $this->getRequest()->getParams();

        if (!$this->formKeyValidator->validate($this->getRequest())) {
            return $this->processReturn(
                __('Invalid Form Key. Please refresh the page.'),
                false
            );
        }

        if (!isset($params['id'])) {
            return $this->processReturn(
                __('Required data missing!'),
                false
            );
        }

        try {
            $this->multipleWishlistRepository->deleteById($params['id']);
        } catch (CouldNotDeleteException $e) {
            return $this->processReturn(
                __('Something went wrong while removing the wishlist.'),
                false
            );
        } catch (NoSuchEntityException $e) {
            return $this->processReturn(
                __('Wishlist doesn\'t exist.'),
                false
            );
        } catch (LocalizedException $e) {
            return $this->processReturn(
                __('Something went wrong.'),
                false
            );
        }

        return $this->processReturn(
            __('Wishlist has been successfully removed.'),
            true
        );
    }
}
