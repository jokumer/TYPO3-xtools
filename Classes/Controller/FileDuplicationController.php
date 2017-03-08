<?php
namespace Jokumer\Xtools\Controller;

use Jokumer\Xtools\Controller\AbstractFileController;
use Jokumer\Xtools\Domain\Repository\FileRepository;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Imaging\Icon;
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
class FileDuplicationController extends AbstractFileController
{
    /**
     * Initialize action
     *
     * @return void
     */
    protected function initializeAction()
    {
        parent::initializeAction();
        // Set additional data
        $this->data['list']['storages'] = $this->getAvailableStorages();
        $this->data['selection']['directory'] = ($this->storage) ? $this->getDirectoryListData($this->currentPathRoot) : null;
        $this->data['selection']['storage'] = $this->storage;
        $this->data['selection']['path'] = $this->currentPathSelected;
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
        if ($this->request->hasArgument('sha1')) {
            $sha1 = $this->request->getArgument('sha1');
        } else {
            $sha1 = null;
        }
        // Assign files duplications
        $filesDuplications = $this->fileRepository->getFilesDuplications($this->storage, $this->currentPathSelected, $sha1);
        $this->view->assign('filesDuplications', $filesDuplications);
    }

    /**
     * Show duplications action
     *
     * @return void
     */
    public function showDuplicationsAction()
    {
        // Assign file duplications
        if ($this->request->hasArgument('sha1')) {
            $sha1 = $this->request->getArgument('sha1');
            $fileDuplications = $this->fileRepository->getFileDuplications($this->storage, $this->currentPathSelected, $sha1);
            if (!empty($fileDuplications)) {
                // Assign fileDuplicationsArray use FileFacade, add references count
                $count = 0;
                $fileDuplicationsArray = [];
                $fileDuplicationsIdentifierForComparisonCheckArray = [];
                $fileDuplicationsAreAffectedForControlCharacter = [];
                foreach ($fileDuplications as $fileDuplication) {
                    $fileDuplicationsArray[$count]['fileFacade'] = new FileFacade($fileDuplication['fileObject']);
                    $fileDuplicationsArray[$count]['fileReferences'] = $fileDuplication['fileReferences'];
                    $fileDuplicationPathAbsolute = $this->getPathAbsolute($this->currentPathRoot . $fileDuplication['fileObject']->getIdentifier());
                    if (file_exists($fileDuplicationPathAbsolute)) {
                        $fileDuplicationsArray[$count]['exists'] = true;
                        // Check indentifier ('B%C3%A5dev%C3%A6rft.jpg' !== 'Ba%CC%8Adev%C3%A6rft.jpg')
                        $isAffectedForControlCharacter = $this->updateUtility->verifyFileNameIsAffectedForControlCharacter($fileDuplication['fileObject']->getIdentifier());
                        if ($isAffectedForControlCharacter) {
                            $fileDuplicationsArray[$count]['isAffectedForControlCharacter'] = true;
                            $fileDuplicationsAreAffectedForControlCharacter[] = $fileDuplication['fileObject']->getUid();
                        }
                        // Register, if file object is a real duplication in file system
                        if (false !== $key = array_search($fileDuplication['fileObject']->getIdentifier(), $fileDuplicationsIdentifierForComparisonCheckArray)) {
                            $fileDuplicationsArray[$count]['realFileDuplication'] = true;
                            $fileDuplicationsArray[$key]['realFileDuplication'] = true;
                        } else {
                            $fileDuplicationsArray[$count]['realFileDuplication'] = false;
                        }
                        $fileDuplicationsIdentifierForComparisonCheckArray[$count] = $fileDuplication['fileObject']->getIdentifier();
                    } else {
                        $fileDuplicationsArray[$count]['exists'] = false;
                    }
                    $count++;
                }
                $this->view->assign('fileDuplications', $fileDuplicationsArray);
                $this->data['fileDuplicationsAreAffectedForControlCharacter'] = $fileDuplicationsAreAffectedForControlCharacter;
            }
        }
        // Assign data
        $this->view->assign('data', $this->data);
    }

    /**
     * Solve duplications action
     *
     * @return void
     */
    public function solveDuplicationsAction()
    {
        $executionTime = $GLOBALS['EXEC_TIME'];
        $preferredFileObject = null;
        $preferredFile = null;
        $replacedFiles = null;
        // Get request
        $sha1 = null;
        if ($this->request->hasArgument('sha1')) {
            $sha1 = $this->request->getArgument('sha1');
        }
        if ($this->request->hasArgument('preferredFileUid')) {
            if (intval($this->request->getArgument('preferredFileUid'))) {
                $preferredFileObject = $this->fileRepository->getFileObject(intval($this->request->getArgument('preferredFileUid')));
                if ($preferredFileObject instanceof File) {
                    $preferredFile = [
                        'fileFacade' => new FileFacade($preferredFileObject),
                        'fileReferences' => $this->fileRepository->getSysFileReferences($preferredFileObject) // not persisted yet!
                    ];
                }
            }
        }
        // Get concerning file duplications
        if ($preferredFileObject && $sha1 && $this->storage) {
            $fileDuplications = $this->fileRepository->getFileDuplications($this->storage, $this->currentPathSelected, $sha1);
            // Remove preferred file from stack
            if (!empty($fileDuplications)) {
                unset($fileDuplications[$preferredFileObject->getUid()]);
                // Replace each duplicated file with preferred file in sys_file_reference
                if (!empty($fileDuplications)) {
                    $replacedFilesTargetPath = $this->appendSlashIfMissing($this->extensionConfigurationBackupPath . $executionTime);
                    GeneralUtility::mkdir_deep($this->getPathAbsolute($replacedFilesTargetPath, true));
                    $replacedFiles = [];
                    $replacedFilesIdentifierForComparisonCheckArray = [];
                    $replacedFilesAreAffectedForControlCharacter = [];
                    foreach ($fileDuplications as $fileUid => $duplicat) {
                        if ($duplicat['fileObject'] instanceof File) {
                            /** @var File $fileObject */
                            $fileObject = $duplicat['fileObject'];
                            $replacedFiles[$fileObject->getUid()]['fileFacade'] = new FileFacade($fileObject);
                            // Change file references
                            if (intval($duplicat['fileReferences']) > 0) {
                                // Get file references
                                $sysFileReferences = $this->fileRepository->getSysFileReferences($fileObject);
                                // Update file reference
                                if (!empty($sysFileReferences)) {
                                    $replacedFiles[$fileObject->getUid()]['sysFileReferences'] = [];
                                    foreach ($sysFileReferences as $key => $fileReference) {
                                        // Replace uid_local with uid of preferred file uid
                                        $updateSysFileReferenceFieldsArray = ['uid_local' => $preferredFileObject->getUid()];
                                        $updateSysFileReferenceResult = $this->fileRepository->updateSysFileReference(intval($fileReference['uid']), $updateSysFileReferenceFieldsArray, true);
                                        if ($updateSysFileReferenceResult) {
                                            $replacedFiles[$fileObject->getUid()]['fileReferences'][$fileReference['uid']] = [$fileReference['uid']];
                                        }
                                    }
                                }
                            }
                            // Remove file from file system
                            $replacedFiles[$fileObject->getUid()]['sourcePath'] = $this->getPathRelative($this->currentPathRoot . $fileObject->getIdentifier());
                            $replacedFiles[$fileObject->getUid()]['targetPath'] = $this->getPathRelative($replacedFilesTargetPath . $preferredFileObject->getUid() . '__' . $fileObject->getUid() . '__' . $fileObject->getName());
                            // Avoid replaced file replaces preferred, if duplicated in db sys_file
                            if ($preferredFileObject->getIdentifier() !== $fileObject->getIdentifier()) {
                                // Check indentifier ('B%C3%A5dev%C3%A6rft.jpg' !== 'Ba%CC%8Adev%C3%A6rft.jpg'), if wrong spelled it could remove preffered file too
                                $isAffectedForControlCharacter = $this->updateUtility->verifyFileNameIsAffectedForControlCharacter($fileObject->getIdentifier());
                                if (!$isAffectedForControlCharacter) {
                                    if (false === $key = array_search($fileObject->getIdentifier(), $replacedFilesIdentifierForComparisonCheckArray)) {
                                        $movingResult = $this->updateUtility->moveFile(
                                            $this->getPathAbsolute($replacedFiles[$fileObject->getUid()]['sourcePath'], false),
                                            $this->getPathAbsolute($replacedFiles[$fileObject->getUid()]['targetPath'], true)
                                        );
                                    }
                                    // Register, if file object is not a real duplication in file system
                                    $replacedFilesIdentifierForComparisonCheckArray[] = $fileObject->getIdentifier();
                                } else {
                                    $replacedFiles[$fileObject->getUid()]['isAffectedForControlCharacter'] = true;
                                    $replacedFilesAreAffectedForControlCharacter[] = $fileObject->getUid();
                                }
                            } else {
                                $replacedFiles[$fileObject->getUid()]['realFileDuplication'] = true;
                            }
                            // Set file as missing in sys_file
                            $updateSysFileFieldsArray = ['missing' => 1];
                            $updateSysFileResult = $this->fileRepository->updateSysFile($fileObject->getUid(), $updateSysFileFieldsArray, true);
                        }
                    }
                    // Log
                    $this->log(
                        [
                            'controller' => __CLASS__,
                            'action' => __FUNCTION__,
                            'currentPathRoot' => $this->currentPathRoot,
                            'preferredFile' => $preferredFileObject,
                            'replacedFiles' => $replacedFiles,
                            'replacedFilesTargetPath' => $replacedFilesTargetPath,
                            'storage' => $this->storage->getUid(),
                        ],
                        $this->getPathAbsolute($replacedFilesTargetPath, true) . '_' . __FUNCTION__ . '.log'
                    );
                }
            }
        }
        // Assign data
        $this->view->assign('data', $this->data);
        $this->view->assign('replacedFiles', $replacedFiles);
        $this->view->assign('replacedFilesAreAffectedForControlCharacter', $replacedFilesAreAffectedForControlCharacter);
        $this->view->assign('executionTime', $executionTime);
        $this->view->assign('replacedFilesTargetPath', $replacedFilesTargetPath);
        $persistenceManager = $this->objectManager->get(\TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager::class);
        // Assign persited preferred file
        $persistenceManager->persistAll();
        if ($preferredFileObject){
            $preferredFile['fileReferences'] = $this->fileRepository->getSysFileReferences($preferredFileObject);
            $this->view->assign('preferredFile', $preferredFile);
        }
    }

    /**
     * Log
     * Write long log file into given directory
     * Write short log into db table sys_log
     * 
     * @param array $logMessageArray
     * @param string $logFile
     * @param bool $logSys default:true
     * @return void
     */
    private function log(array $logMessageArray, $logFile = '', $logSys = true)
    {
        if (!empty($logMessageArray)) {
            // Log file (long)
            if ($logFile) {
                $fh = fopen($logFile, 'w');
                fwrite($fh, serialize($logMessageArray));
                fclose($fh);
            }
            // Log system (short)
            if ($logSys) {
                $type = 2; // sys_log type 2:File
                $this->updateUtility->logMessage(
                    serialize([
                        'controller' => $logMessageArray['controller'],
                        'action' => $logMessageArray['action'],
                        'storageUid' => $logMessageArray['storage'],
                        'currentPathRoot' => $logMessageArray['currentPathRoot'],
                        'preferredFileUid' => $logMessageArray['preferredFile']->getUid()
                    ]),
                    $type
                );
            }
        }
    }
}
