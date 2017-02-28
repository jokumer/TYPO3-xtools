<?php
namespace Jokumer\Xtools\Controller;

use Jokumer\Xtools\Controller\AbstractFileController;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class FileAndFolderPermissionController
 *
 * @package TYPO3
 * @subpackage tx_xtools
 * @author 2017 J.Kummer <typo3 et enobe dot de>, enobe.de
 * @copyright Copyright belongs to the respective authors
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class FileAndFolderPermissionController extends AbstractFileController
{
    /**
     * Initialize action
     *
     * @return void
     */
    protected function initializeAction()
    {
        parent::initializeAction();
    }
    
    /**
     * Index action
     *
     * @return void
     */
    public function indexAction()
    {
        // Run action before getting list
        if ($this->request->hasArgument('data')) {
            $requestData = $this->request->getArgument('data');
            if (is_array($requestData) && isset($requestData['form'])) {
                $this->runAction($requestData);
            }
        }
        // Get selection
        $this->data['selection']['directory'] = $this->getDirectoryListData($this->currentPathRoot);
        $this->data['path']['site'] = PATH_site;
        $this->data['path']['current']['selected'] = $this->currentPathSelected;
        $this->data['path']['current']['properties'] = $this->getDirectoryProperties(PATH_site, $this->currentPathSelected);
        $this->data['icons']['apps-filetree-folder-default'] = $this->iconFactory->getIcon('apps-filetree-folder-default', Icon::SIZE_SMALL);
        $this->data['icons']['apps-filetree-folder-opened'] = $this->iconFactory->getIcon('apps-filetree-folder-opened', Icon::SIZE_SMALL);
        $this->data['targetPermissions']['fileCreateMask'] = isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['fileCreateMask']) ? $GLOBALS['TYPO3_CONF_VARS']['SYS']['fileCreateMask'] : '0644';
        $this->data['targetPermissions']['folderCreateMask'] = isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['folderCreateMask']) ? $GLOBALS['TYPO3_CONF_VARS']['SYS']['folderCreateMask'] : '0755';
        $this->view->assign('data', $this->data);
    }

    /**
     * Run action
     *
     * @param array $requestData
     * @return void
     */
    private function runAction($requestData)
    {
        $flashMessageBody = null;
        $flashMessageTitle = 'FileAndFolderPermissionAction';
        if (isset($requestData['form'])) {
            if (@is_dir($this->getPathAbsolute($this->currentPathSelected))) {
                if (isset($requestData['form']['action']) && $requestData['form']['action'] !== '') {
                    if ($requestData['form']['action']['fixpermissions']) {
                        if ($requestData['form']['action']['fixpermissions_recursive']) {
                            $recursive = true;
                        } else {
                            $recursive = false;
                        }
                        $fixPermissions = GeneralUtility::fixPermissions($this->getPathAbsolute($this->currentPathSelected), $recursive);
                        $flashMessageBody = 'fixPermissions::done';
                        $flashMessageSeverity = AbstractMessage::OK;
                    } else {
                        $flashMessageBody = 'No action selected';
                        $flashMessageSeverity = AbstractMessage::ERROR;
                    }
                } else {
                    $flashMessageBody = 'No action';
                    $flashMessageSeverity = AbstractMessage::ERROR;
                }
            } else {
                $flashMessageBody = 'No directory: ' . $this->getPathAbsolute($this->currentPathSelected);
                $flashMessageSeverity = AbstractMessage::ERROR;
            }
        }
        if ($flashMessageBody) {
            $this->addFlashMessage($flashMessageBody, $flashMessageTitle);
        }
    }
}
