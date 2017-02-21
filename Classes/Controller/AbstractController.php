<?php
namespace Jokumer\Xtools\Controller;

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
     * Initialize view
     *
     * @param ViewInterface $view
     * @return void
     */
    protected function initializeView(ViewInterface $view)
    {
        parent::initializeView($view);
        // Assign controller/action name for layout
        $view->assign('controllerName', $this->request->getControllerName());
        $view->assign('actionName', $this->request->getControllerActionName());
        // Assign extension configuration
        $this->initializeExtensionConfiguration();
        $view->assign('extensionConfiguration', $this->extensionConfiguration);
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
}
