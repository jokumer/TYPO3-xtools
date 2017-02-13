<?php
namespace Jokumer\Xtools\Controller;

use Jokumer\Xtools\Controller\AbstractController;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;

/**
 * Class FileAndFolderPermissionController
 *
 * @package TYPO3
 * @subpackage tx_xtools
 * @author 2017 J.Kummer <typo3 et enobe dot de>, enobe.de
 * @copyright Copyright belongs to the respective authors
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class FileAndFolderPermissionController extends AbstractController
{
    /**
     * Current path absolute
     * 
     * @var string
     */
    protected $currentPathAbsolute = null;

    /**
     * Current path relative
     *
     * @var string
     */
    protected $currentPathRelative = null;

    /**
     * Current path selected
     *
     * @var string
     */
    protected $currentPathSelected = null;
    
    /**
     * @var IconFactory
     */
    protected $iconFactory;

    /**
     * Construct
     */
    public function __construct()
    {
        parent::__construct();
        $this->iconFactory = GeneralUtility::makeInstance(IconFactory::class);
    }

    /**
     * Index action
     *
     * @return void
     */
    public function indexAction()
    {
        $data = [];
        $this->currentPathSelected = $this->getCurrentPathSelected();
        // Run action before getting list
        if ($this->request->hasArgument('data')) {
            $requestData = $this->request->getArgument('data');
            if (isset($requestData['form'])) {
                $data['action'] = $this->runAction($requestData);
            }
        }
        // Get selection
        $data['selection'] = $this->getDirectoryListData();
        $data['path']['site'] = PATH_site;
        $data['path']['current']['absolute'] = $this->currentPathAbsolute;
        $data['path']['current']['relative'] = $this->currentPathRelative;
        $data['path']['current']['selected'] = $this->currentPathSelected;
        $data['path']['current']['properties'] = $this->getDirectoryProperties(PATH_site, $this->currentPathSelected);
        $data['icons']['apps-filetree-folder-default'] = $this->iconFactory->getIcon('apps-filetree-folder-default', Icon::SIZE_SMALL);
        $data['icons']['apps-filetree-folder-opened'] = $this->iconFactory->getIcon('apps-filetree-folder-opened', Icon::SIZE_SMALL);
        $data['targetPermissions']['fileCreateMask'] = isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['fileCreateMask']) ? $GLOBALS['TYPO3_CONF_VARS']['SYS']['fileCreateMask'] : '0644';
        $data['targetPermissions']['folderCreateMask'] = isset($GLOBALS['TYPO3_CONF_VARS']['SYS']['folderCreateMask']) ? $GLOBALS['TYPO3_CONF_VARS']['SYS']['folderCreateMask'] : '0755';
        $this->view->assign('data', $data);
    }

    /**
     * Get data
     *
     * @param array $data
     * @return array $data
     */
    protected function getData($data)
    {
        return $data;
    }

    /**
     * Get current path selected
     *
     * @return array $currentPathSelected
     */
    protected function getCurrentPathSelected()
    {
        $currentPathSelected = null;
        // Submitted via form
        if ($this->request->hasArgument('data')) {
            $requestData = $this->request->getArgument('data');
            if (isset($requestData['form']['pathSelected']) && $requestData['form']['pathSelected'] !== '') {
                $pathSubmitted = $requestData['form']['pathSelected'];
                if (@is_dir($this->getPathAbsolute($pathSubmitted))) {
                    $currentPathSelected = $pathSubmitted;
                }
            }
        }
        // Selected via link
        if ($this->request->hasArgument('selection')) {
            $selection = $this->request->getArgument('selection');
            $pathSelected = $selection['path'] . $selection['directory'];
            if (@is_dir($this->getPathAbsolute($pathSelected))) {
                $currentPathSelected = $pathSelected;
            }
        }
        return $currentPathSelected;
    }

    /**
     * Run action
     *
     * @param array $requestData
     * @return void
     */
    protected function runAction($requestData)
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
            $this->addFlashMessage($flashMessageBody, $flashMessageTitle, $flashMessageSeverity);
        }
    }

    /**
     * Get directory list data
     * Includes subdirectories by selections
     *
     * @return array $directoryListData
     */
    protected function getDirectoryListData()
    {
        $directoryListData = $this->getDirectoryData($this->currentPathRelative, '');
        // Get selection
        $selectedPath = $this->currentPathSelected;
        if ($this->request->hasArgument('selection')) {
            $selection = $this->request->getArgument('selection');
            $selectedPath = $selection['path'] . $selection['directory'];
        }
        if ($selectedPath) {
            $selectedLevels = GeneralUtility::trimExplode('/', $selectedPath);
            if (!empty($selectedLevels)) {
                foreach ($selectedLevels as $selectedLevelKey => $selectedLevelDirectory) {
                    $this->currentPathRelative .= $selectedLevelDirectory . '/';
                    $directoryListData = $this->addListSelection($directoryListData, $this->currentPathRelative, $selectedLevelDirectory);
                }
                $this->currentPathAbsolute = $this->getPathAbsolute($this->currentPathRelative);
            }
        }
        return $directoryListData;
    }

    /**
     * Get directory data
     * 
     * @param string $path
     * @param string $directoryName
     * @return array $directoryData
     */
    protected function getDirectoryData($path, $directoryName)
    {
        $absolutePath = $this->getPathAbsolute($path);
        $relativePath = $this->getPathRelative($path);
        $directories = GeneralUtility::get_dirs($absolutePath);
        if (is_array($directories) && !empty($directories)) {
            $directoriesData = [];
            foreach ($directories as $key => $val) {
                $directoriesData[$key] = $this->getDirectoryProperties($absolutePath, $val);
            }
        } else {
            $directoriesData = false;
        }
        return [
            'path' => $relativePath,
            'directory' => $directoryName,
            'directories' => $directoriesData
        ];
    }

    /**
     * Get directory properties
     *
     * @param string $path
     * @param string $directory
     * @return array $details
     */
    protected function getDirectoryProperties($absolutePath, $directory)
    {
        $directoryProperties = [];
        $directoryProperties['name'] = $directory;
        $directoryProperties['ownerId'] = fileowner($absolutePath . $directory);
        $directoryProperties['groupId'] = filegroup($absolutePath . $directory);
        $directoryProperties['permissions'] = substr(sprintf('%o', fileperms($absolutePath . $directory)), -4);
        return $directoryProperties;
    }

    /**
     * Add list selection
     *
     * @param array $list
     * @param string $path
     * @param string $directory
     * @return array $list
     */
    protected function addListSelection($list, $path, $directory)
    {
        // Append directory data to last added selection
        if (isset($list['selection'])) {
            $list['selection'] = $this->addListSelection($list['selection'], $path, $directory);
        } else {
            $list['selection'] = $this->getDirectoryData($path, $directory);
        }
        return $list;
    }

    /**
     * Get path relative
     *
     * @param string $path
     * @return string $path
     */
    protected function getPathRelative($path)
    {
        $pos = strpos($path, PATH_site);
        if ($pos !== false) {
            $substring = substr($path, strlen(PATH_site));
            if ($substring) {
                $path = GeneralUtility::dirname($substring);
            } else {
                $path = '';
            }
        }
        return $path;
    }

    /**
     * Get path absolute
     *
     * @param string $path
     * @return string $path
     */
    protected function getPathAbsolute($path)
    {
        $pos = strpos($path, PATH_site);
        if ($pos === false) {
            $path = PATH_site . $path;
        }
        return $path;
    }
}
