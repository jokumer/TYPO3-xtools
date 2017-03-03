<?php
namespace Jokumer\Xtools\Controller;

use Jokumer\Xtools\Controller\AbstractController;
use Jokumer\Xtools\Domain\Repository\FileRepository;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;

/**
 * Class AbstractFileController
 *
 * @package TYPO3
 * @subpackage tx_xtools
 * @author 2017 J.Kummer <typo3 et enobe dot de>, enobe.de
 * @copyright Copyright belongs to the respective authors
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class AbstractFileController extends AbstractController
{
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
     * Available storages
     *
     * @var array $availableStorages
     */
    protected $availableStorages = [];

    /**
     * Current path root
     *
     * @var string
     */
    protected $currentPathRoot = null;

    /**
     * Current path selected
     *
     * @var string
     */
    protected $currentPathSelected = null;

    /**
     * Extension configuration backup path
     *
     * @var string
     */
    protected $extensionConfigurationBackupPath = 'typo3temp/tx_xtools/';

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
        $this->iconFactory = GeneralUtility::makeInstance(IconFactory::class);
    }

    /**
     * Initialize action
     *
     * @return void
     */
    protected function initializeAction()
    {
        parent::initializeAction();
        // Set current path's by selection
        if ($this->request->hasArgument('selection')) {
            $selection = $this->request->getArgument('selection');
            if (intval($selection['storage'])) {
                $this->setStorage(intval($selection['storage']));
                $storageConfiguration = $this->storage->getConfiguration();
                if (isset($storageConfiguration['basePath'])) {
                    $currentPathRoot = $storageConfiguration['basePath'];
                } else {
                    $currentPathRoot = '';
                }
            } else {
                $currentPathRoot = '';
            }
        }
        $currentPathRoot = $this->appendSlashIfMissing($currentPathRoot);
        $this->currentPathRoot = $this->getPathRelative($currentPathRoot);
        $this->currentPathSelected = $this->getCurrentPathSelected();
        if (isset($this->extensionConfiguration['backup_path'])) {
            $this->extensionConfigurationBackupPath = $this->appendSlashIfMissing($this->extensionConfiguration['backup_path']);
        }
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
            $isDir = $this->currentPathRoot . $pathSelected;
            if (@is_dir($this->getPathAbsolute($isDir))) {
                $currentPathSelected = $pathSelected;
            }
        }
        return $currentPathSelected;
    }

    /**
     * Get directory list data
     * Includes subdirectories by selections
     *
     * @param string $path
     * @return array $directoryListData
     */
    protected function getDirectoryListData($path = null)
    {
        $relativePath = $this->getPathRelative($path);
        $directoryListData = $this->getDirectoryData($relativePath, '');
        // Get selection
        if ($this->currentPathSelected) {
            $selectedLevels = GeneralUtility::trimExplode('/', $this->currentPathSelected);
            if (!empty($selectedLevels)) {
                foreach ($selectedLevels as $selectedLevelKey => $selectedLevelDirectory) {
                    $relativePath .= $this->appendSlashIfMissing($selectedLevelDirectory);
                    $directoryListData = $this->addListSelection($directoryListData, $relativePath, $selectedLevelDirectory);
                }
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
            'path' => $substring = substr($relativePath, strlen($this->currentPathRoot)),
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
        $path = $this->removeUnnecassarySlash($path);
        return $path;
    }

    /**
     * Get path absolute
     *
     * @param string $path
     * @param bool $ignoreCurrentPathRoot dont add current root path (fx from storage) useful when move files to absolute path
     * @return string $path
     */
    protected function getPathAbsolute($path, $ignoreCurrentPathRoot = false)
    {
        $pos1 = strpos($path, $this->currentPathRoot);
        if ($pos1 === false && $ignoreCurrentPathRoot === false) {
            $path = $this->currentPathRoot . $path;
        }
        $pos2 = strpos($path, PATH_site);
        if ($pos2 === false) {
            $path = PATH_site . $path;
        }
        $path = $this->removeUnnecassarySlash($path);
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
     * Get available storages
     *
     * @return array
     */
    protected function getAvailableStorages()
    {
        return $this->fileRepository->getStorages();
    }

    /**
     * Append slash if missing
     *
     * @param string $path
     * @return string $path
     */
    protected function appendSlashIfMissing($path = '')
    {
        if (substr($path, -1) != '/') {
            $path .= '/';
        }
        return $path;
    }

    /**
     * Prepend slash if missing
     *
     * @param string $path
     * @return string $path
     */
    protected function prependSlashIfMissing($path = '')
    {
        if (substr($path, 1) != '/') {
            $path = '/' . $path;
        }
        return $path;
    }

    /**
     * Remove unnecassary slash
     * Changes from '//' to '/'
     *
     * @param string $path
     * @return string $path
     */
    protected function removeUnnecassarySlash($path = '')
    {
        $path = preg_replace('#/+#','/', $path);
        return $path;
    }
}
