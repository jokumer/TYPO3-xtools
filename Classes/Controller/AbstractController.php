<?php
namespace Jokumer\Xtools\Controller;

use Jokumer\Xtools\Utility\UpdateUtility;
use Jokumer\Xtools\Domain\Repository\FileRepository;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;

/**
 * Class AbstractController
 *
 * @package TYPO3
 * @subpackage tx_xtools
 * @author 2017 J.Kummer <typo3 et enobe dot de>, enobe.de
 * @copyright Copyright belongs to the respective authors
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class AbstractController extends ActionController
{
    /**
     * Extension configuration
     * 
     * @var array
     */
    protected $extensionConfiguration = [];

    /**
     * UpdateUtility
     *
     * @var UpdateUtility
     */
    protected $updateUtility = null;

    /**
     * Data, assigned to view
     *
     * @var array data
     */
    protected $data = [];

    /**
     * AbstractController constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->updateUtility = GeneralUtility::makeInstance(UpdateUtility::class);
    }

    /**
     * Initialize extension configuration
     *
     * @return void
     */
    protected function initializeExtensionConfiguration()
    {
        $this->extensionConfiguration = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['xtools']);
    }

    /**
     * Initialize view
     *
     * @param ViewInterface $view
     * @return void
     */
    protected function initializeView(ViewInterface $view)
    {
        parent::initializeView($view);
        // Assign controller/action name
        $view->assign('controllerName', $this->request->getControllerName());
        $view->assign('actionName', $this->request->getControllerActionName());
        // Assign extension configuration
        $this->initializeExtensionConfiguration();
        $view->assign('extensionConfiguration', $this->extensionConfiguration);
    }

    /**
     * Get data
     *
     * @return array $data
     */
    protected function getData()
    {
        return $this->data;
    }
}
