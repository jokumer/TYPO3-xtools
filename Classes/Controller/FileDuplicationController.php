<?php
namespace Jokumer\Xtools\Controller;

use Jokumer\Xtools\Controller\AbstractController;
use Jokumer\Xtools\Domain\Repository\FileRepository;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Filelist\FileFacade;

/**
 * Class FileDuplicationController
 *
 * @package TYPO3
 * @subpackage tx_xtools
 * @author 2017 J.Kummer <typo3 et enobe dot de>, enobe.de
 * @copyright Copyright belongs to the respective authors
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class FileDuplicationController extends AbstractController
{
    /**
     * File repository
     *
     * @var FileRepository
     */
    protected $fileRepository;

    /**
     * IconFactory
     * 
     * @var IconFactory
     */
    protected $iconFactory;

    /**
     * Storage, current selected
     * 
     * @var ResourceStorage
     */
    protected $storage;

    /**
     * Directory, current selected
     * 
     * @var string $directory
     */
    protected $directory;

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
     * Construct
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
        // Set available storages
        $this->setAvailableStorages();
        // Set selection
        if ($this->request->hasArgument('selection')) {
            $selection = $this->request->getArgument('selection');
            if (intval($selection['storage'])) {
                $this->setStorage(intval($selection['storage']));
                $this->setAvailableDirectories();
            }
            if ($selection['directory']) {
                $this->setDirectory($selection['directory']);
            }
        }
        // Set additional data
        $this->data['list']['storages'] = $this->getAvailableStorages();
        $this->data['list']['directories'] = $this->getAvailableDirectories();
        $this->data['selection']['storage'] = $this->storage;
        $this->data['selection']['directory'] = $this->directory;
        $this->data['icons']['apps-filetree-folder-default'] = $this->iconFactory->getIcon('apps-filetree-folder-default', Icon::SIZE_SMALL);
        $this->data['icons']['apps-filetree-folder-opened'] = $this->iconFactory->getIcon('apps-filetree-folder-opened', Icon::SIZE_SMALL);
        $this->data['icons']['apps-filetree-folder-locked'] = $this->iconFactory->getIcon('apps-filetree-folder-locked', Icon::SIZE_SMALL);
    }

    /**
     * Index action
     *
     * @return void
     */
    public function indexAction()
    {
        // Assign data
        $this->view->assign('data', $this->data);
        // Assign files duplications
        $filesDuplications = $this->fileRepository->getFilesDuplications($this->storage, $this->directory);
        $this->view->assign('filesDuplications', $filesDuplications);
    }

    /**
     * Show duplications action
     *
     * @return void
     */
    public function showDuplicationsAction()
    {
        // Assign data
        $this->view->assign('data', $this->data);
        // Assign file duplications
        if ($this->request->hasArgument('sha1')) {
            $sha1 = $this->request->getArgument('sha1');
            $fileDuplications = $this->fileRepository->getFileDuplications($this->storage, $this->directory, $sha1);
            if (!empty($fileDuplications)) {
                // Assign fileDuplicationsArray use FileFacade, add usage count
                foreach ($fileDuplications as $key => $fileDuplication) {
                    $fileDuplicationsArray[$key]['fileFacade'] = new FileFacade($fileDuplication['fileObject']);
                    $fileDuplicationsArray[$key]['usage'] = $fileDuplication['usage'];
                }
                $this->view->assign('fileDuplications', $fileDuplicationsArray);
            }
        }
    }

    /**
     * Set storage
     *
     * @param intval $storageUid
     * @return void
     */
    protected function setStorage($storageUid)
    {
        if (intval($storageUid)){
            /** @var StorageRepository $storageRepository */
            $storageRepository = GeneralUtility::makeInstance(StorageRepository::class);
            /** @var ResourceStorage $storage */
            $this->storage = $storageRepository->findByUid($storageUid);
        }
    }

    /**
     * Get storage
     *
     * @return void
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
     * @return void
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
     * @return void
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
     * @return void
     */
    protected function getAvailableDirectories()
    {
        return $this->availableDirectories;
    }
}
