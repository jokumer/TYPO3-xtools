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
        // Assign files duplications
        $filesDuplications = $this->fileRepository->getFilesDuplications($this->storage, $this->currentPathSelected);
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
            $fileDuplications = $this->fileRepository->getFileDuplications($this->storage, $this->currentPathSelected, $sha1);
            if (!empty($fileDuplications)) {
                // Assign fileDuplicationsArray use FileFacade, add references count
                $count = 0;
                $fileDuplicationsArray = [];
                foreach ($fileDuplications as $key => $fileDuplication) {
                    $fileDuplicationsArray[$count]['fileFacade'] = new FileFacade($fileDuplication['fileObject']);
                    $fileDuplicationsArray[$count]['references'] = $fileDuplication['references'];
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
        $executionTime = $GLOBALS['EXEC_TIME'];
        $preferredFileUid = null;
        $preferredFile = null;
        $replacedFiles = null;
        $replacedFileReferences = null;
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
            $fileDuplications = $this->fileRepository->getFileDuplications($this->storage, $this->currentPathSelected, $sha1);
            // Remove preferred file from stack
            if (!empty($fileDuplications)) {
                unset($fileDuplications[$preferredFileUid]);
                // Replace each duplicated file with preferred file in sys_file_reference
                if (!empty($fileDuplications)) {
                    // @todo: make backup path configurable
                    $replacedFilesTargetPath = PATH_site . $this->extensionConfigurationBackupPath . $executionTime . '/';
                    $mkdir = GeneralUtility::mkdir_deep($replacedFilesTargetPath);
                    if ($mkdir !== false) {
                        $replacedFiles = [];
                        foreach ($fileDuplications as $fileUid => $duplicat) {
                            if ($duplicat['fileObject'] instanceof File) {
                                /** @var File $fileObject */
                                $fileObject = $duplicat['fileObject'];
                                $replacedFiles[$fileObject->getUid()]['identifier'] = $fileObject->getIdentifier();
                                if (intval($duplicat['references']) > 0) {
                                    // Get file references
                                    $sysFileReferences = $this->fileRepository->getSysFileReferences($fileObject);
                                    // Update file reference
                                    if (!empty($sysFileReferences)) {
                                        $replacedFiles[$fileObject->getUid()]['sysFileReferences'] = [];
                                        $replacedFileReferences = [];
                                        foreach ($sysFileReferences as $key => $sysFileReference) {
                                            // Replace uid_local with uid of preferred file uid
                                            $updateSysFileReferenceFieldsArray = ['uid_local' => intval($preferredFileUid)];
                                            $updateSysFileReferenceResult = $this->fileRepository->updateSysFileReference(intval($sysFileReference['uid']), $updateSysFileReferenceFieldsArray, true);
                                            if ($updateSysFileReferenceResult) {
                                                $replacedFileReferences[$sysFileReference['uid']] = $sysFileReference;
                                                $replacedFiles[$fileObject->getUid()]['sysFileReferences'][$sysFileReference['uid']] = [$sysFileReference['uid']];
                                            }
                                        }
                                    }
                                }
                                // Remove file from file system
                                $target = $replacedFilesTargetPath . $fileObject->getUid() . '__' . $fileObject->getName();
                                $source = $this->getPathAbsolute($this->currentPathRoot . $fileObject->getIdentifier());
                                $movingResult = $this->updateUtility->moveFile($source, $target);
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
                                'preferredFileUid' => $preferredFileUid,
                                'replacedFiles' => serialize($replacedFiles),
                                'replacedFilesTargetPath' => $replacedFilesTargetPath,
                                'replacedFileReferences' => [],
                                'storage' => $this->storage,
                            ],
                            $replacedFilesTargetPath . '_' . __FUNCTION__ . '.log'
                        );
                    }
                }
            }
        }
        // Assign data
        $this->view->assign('data', $this->data);
        $this->view->assign('preferredFile', $preferredFile);
        $this->view->assign('replacedFiles', $replacedFiles);
        $this->view->assign('replacedFileReferences', $replacedFileReferences);
        $this->view->assign('executionTime', $executionTime);
        $this->view->assign('replacedFilesTargetPath', $replacedFilesTargetPath);
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
                        'storageUid' => $logMessageArray['storage']->getUid(),
                        'currentPathRoot' => $logMessageArray['currentPathRoot'],
                        'preferredFileUid' => $logMessageArray['preferredFileUid']
                    ]),
                    $type
                );
            }
        }
    }
}
