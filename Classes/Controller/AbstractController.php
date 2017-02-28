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
     * @var IconFactory
     */
    protected $iconFactory;

    /**
     * Storage repository
     *
     * @var StorageRepository
     */
    protected $storageRepository;

    /**
     * File repository
     *
     * @var FileRepository
     */
    protected $fileRepository;

    /**
     * Storage, current selected
     *
     * @var ResourceStorage
     */
    protected $storage = null;

    /**
     * Directory, current selected
     *
     * @var string $directory
     */
    protected $directory = null;

    /**
     * Available storages
     *
     * @var array $availableStorages
     */
    protected $availableStorages = [];

    /**
     * Available directories
     *
     * @var array $availableDirectories
     */
    protected $availableDirectories = [];

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
     * Data, assigned to view
     *
     * @var array data
     */
    protected $data = [];

    /**
     * Inject FileRepository
     *
     * @param FileRepository $fileRepository
     */
    public function injectFileRepository(FileRepository $fileRepository) {
        $this->fileRepository = $fileRepository;
    }

    /**
     * Inject StorageRepository
     *
     * @param StorageRepository $StorageRepository
     */
    public function injectStorageRepository(StorageRepository $storageRepository) {
        $this->storageRepository = $storageRepository;
    }

    /**
     * AbstractController constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->updateUtility = GeneralUtility::makeInstance(UpdateUtility::class);
        $this->iconFactory = GeneralUtility::makeInstance(IconFactory::class);
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

    /**
     * Set storage
     *
     * @param integer $storageUid
     * @return void
     */
    protected function setStorage($storageUid)
    {
        if (intval($storageUid)){
            $this->storage = $this->storageRepository->findByUid($storageUid);
        }
    }

    /**
     * Get storage
     *
     * @return ResourceStorage
     */
    protected function getStorage()
    {
        return $this->storage;
    }

    /**
     * Set directory
     *
     * @param string $directory
     * @return void
     */
    protected function setDirectory($directory)
    {
        if ($directory) {
            $this->directory = $directory;
        }
    }

    /**
     * Get directory
     *
     * @return string
     */
    protected function getDirectory()
    {
        return $this->directory;
    }

    /**
     * Set available storages
     *
     * @return void
     */
    protected function setAvailableStorages()
    {
        $this->availableStorages = $this->fileRepository->getStorages();
    }

    /**
     * Get available storages
     *
     * @return array
     */
    protected function getAvailableStorages()
    {
        return $this->availableStorages;
    }

    /**
     * Set available directories
     *
     * @return void
     */
    protected function setAvailableDirectories()
    {
        if ($this->storage){
            $this->availableDirectories = $this->storage->getFolderIdentifiersInFolder('');
        }
    }

    /**
     * Get available directories
     *
     * @return array
     */
    protected function getAvailableDirectories()
    {
        return $this->availableDirectories;
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
