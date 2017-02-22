<?php
namespace Jokumer\Xtools\Controller;

use Jokumer\Xtools\Controller\AbstractController;
use Jokumer\Xtools\Domain\Repository\FileRepository;
use TYPO3\CMS\Core\Resource\File;
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
                $count = 0;
                foreach ($fileDuplications as $key => $fileDuplication) {
                    $fileDuplicationsArray[$count]['fileFacade'] = new FileFacade($fileDuplication['fileObject']);
                    $fileDuplicationsArray[$count]['usage'] = $fileDuplication['usage'];
                    $count++;
                }
                $this->view->assign('fileDuplications', $fileDuplicationsArray);
            }
        }
    }

    /**
     * Solve duplications action
     *
     * @return void
     */
    public function solveDuplicationsAction()
    {
        $preferredFileUid = null;
        $preferredFile = null;
        $replacedFiles = null;
        // Get request
        if ($this->request->hasArgument('preferredFileUid')) {
            $preferredFileUid = $this->request->getArgument('preferredFileUid');
            $preferredFile = $this->fileRepository->getFileObject($preferredFileUid);
        }
        $sha1 = null;
        if ($this->request->hasArgument('sha1')) {
            $sha1 = $this->request->getArgument('sha1');
        }
        // Get concerning file duplications
        if (intval($preferredFileUid) && $sha1 && $this->storage) {
            $fileDuplications = $this->fileRepository->getFileDuplications($this->storage, $this->directory, $sha1);
            // Remove preferred file from stack
            if (!empty($fileDuplications)) {
                unset($fileDuplications[$preferredFileUid]);
                // Replace each duplicated file with preferred file in sys_file_reference
                if (!empty($fileDuplications)) {
                    // @todo: Backup sys_file_reference
                    #$tableNameSuffix = '_bakfileduplicationsolves_' . $GLOBALS['EXEC_TIME'];
                    #$this->updateUtility->backupDBTables(['sys_file_reference'], $tableNameSuffix);
                    $replacedFiles = [];
                    foreach ($fileDuplications as $fileUid => $duplicat) {
                        if (intval($duplicat['usage']) > 0 && $duplicat['fileObject'] instanceof File) {
                            $fileObjectUid = $duplicat['fileObject']->getUid();
                            $replacedFiles[$duplicat['fileObject']->getUid()] = [
                                'fileObject' => $duplicat['fileObject']
                            ];
                            // Get file references
                            $sysFileReferences = $this->fileRepository->getSysFileReferences($duplicat['fileObject'], $this->storage, $this->directory);
                            // Update file reference
                            if (!empty($sysFileReferences)) {
                                $replacedFiles[$duplicat['fileObject']->getUid()]['sysFileReferences'] = $sysFileReferences;
                                foreach ($sysFileReferences as $key => $sysFileReference) {
                                    // Replace uid_local with uid of preferred file uid
                                    $updateFieldsArray = ['uid_local' => intval($preferredFileUid)];
                                    $this->fileRepository->updateSysFileReference(intval($sysFileReference['uid']), $updateFieldsArray, true);
                                }
                            }
                            // Remove file from file system
                            # @todo: rm -f file
                            // Delete or set file as missing in sys_file
                            # @todo: update db sys_file table
                            // Assing results to view
                            # @todo: $this->view->assign('fileReplacements', $fileReplacements);
                        }
                        $fileReferences = null;
                    }
                }
            }
        }
        $this->view->assign('preferredFile', $preferredFile);
        $this->view->assign('replacedFiles', $replacedFiles);
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
