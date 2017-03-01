<?php
namespace Jokumer\Xtools\Domain\Repository;

use Jokumer\Xtools\Utility\UpdateUtility;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class FileRepository
 *
 * @package TYPO3
 * @subpackage tx_xtools
 * @author 2017 J.Kummer <typo3 et enobe dot de>, enobe.de
 * @copyright Copyright belongs to the respective authors
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class FileRepository extends \TYPO3\CMS\Core\Resource\FileRepository
{
    /**
     * Get storages
     * Returns array of sys_file_storage entries
     *
     * @return QueryResultInterface
     */
    public function getStorages($limit = 1000) {
        $storages = null;
        $rows = $this->getDatabaseConnection()->exec_SELECTgetRows(
            '*',
            'sys_file_storage',
            'deleted = 0',
            '',
            '',
            $limit,
            'uid'
        );
        if (!empty($rows)) {
            $storages = [];
            foreach ($rows as $row) {
                $storages[$row['uid']] = $row;
            }
        }
        return $storages;
    }

    /**
     * Get files duplications
     * Returns array of first found sys_file's, which has multiple duplications by sha1 value
     *
     * @param ResourceStorage $storageUid
     * @param string $directory
     * @param integer $limit
     * @return QueryResultInterface
     */
    public function getFilesDuplications($storage = null, $directory = null, $limit = 10000) {
        $filesDuplications = null;
        if ($storage instanceof ResourceStorage) {
            $addWhereArray['storage'] = 'storage = ' . $storage->getUid();
            if ($directory !== null) {
                $directorySearchPhrase = ($directory === '') ? '\/' : '\/' . $directory . '\/';
                $addWhereArray['identifier'] = 'identifier LIKE \'' . $directorySearchPhrase . '%\'';
            }
            $addWhereArray['excludeProcessedFiles'] = '(identifier NOT LIKE \'%/_processed_/%\' AND pid = 0)';
            $addWhereArray['missing'] = '(missing = 0)';
            $addWhere = ' AND ' . implode(' AND ', $addWhereArray);
            $rows = $this->getDatabaseConnection()->exec_SELECTgetRows(
                'uid, sha1, name, type, size, count(uid) totalCount',
                'sys_file',
                '1=1' . $addWhere,
                'sha1 HAVING COUNT(uid) >= 2',
                'totalCount DESC',
                $limit,
                'uid'
            );
            if (!empty($rows)) {
                $filesDuplications = $rows;
            }
        }
        return $filesDuplications;
    }

    /**
     * Get file duplications
     * Returns array of all found sys_file's, which has given sha1 value
     *
     * @param ResourceStorage $storageUid
     * @param string $directory
     * @param string $sha1
     * @param integer $limit
     * @return QueryResultInterface
     */
    public function getFileDuplications($storage = null, $directory = null, $sha1 = '', $limit = 10000) {
        $fileDuplications = null;
        if ($storage instanceof ResourceStorage) {
            $addWhereArray['storage'] = 'sf.storage = ' . $storage->getUid();
            if ($directory !== null) {
                $directorySearchPhrase = ($directory === '') ? '\/' : '\/' . $directory . '\/';
                $addWhereArray['identifier'] = 'identifier LIKE \'' . $directorySearchPhrase . '%\'';
            }
            $addWhereArray['excludeProcessedFiles'] = '(sf.identifier NOT LIKE \'%/_processed_/%\' AND sf.pid = 0)';
            $addWhereArray['missing'] = '(sf.missing = 0)';
            $addWhere = ' AND ' . implode(' AND ', $addWhereArray);
            if ($sha1) {
                $rows = $this->getDatabaseConnection()->exec_SELECTgetRows(
                    'sf.uid, sf.sha1, sf.name, count(sf.uid) sfrCount',
                    'sys_file AS sf'
                    . ' LEFT JOIN sys_file_reference AS sfr ON sf.uid = sfr.uid_local'
                    . ' LEFT JOIN sys_file_metadata AS sfm ON sf.uid = sfm.file',
                    'sf.sha1 = ' . $this->getDatabaseConnection()->fullQuoteStr($sha1, 'sys_file') . $addWhere,
                    'sf.uid',
                    // @todo: group order by meta (title, description, caption, keyword..)
                    'sfrCount DESC, sf.name ASC, sfm.title DESC, sfm.description DESC, sfm.caption DESC, sfm.keywords DESC',
                    $limit,
                    'uid'
                );
                if (!empty($rows)) {
                    $fileDuplications = [];
                    foreach ($rows as $key => $row) {
                        try {
                            $fileObject = $this->factory->getFileObject($row['uid']);
                            $fileDuplications[$key]['fileObject'] = $fileObject;
                            $fileDuplications[$key]['references'] = $this->getSysFileReferences($fileObject);
                        } catch (ResourceDoesNotExistException $exception) {
                            // No handling, just omit the invalid reference uid
                        }
                    }
                }
            }
        }
        return $fileDuplications;
    }

    /**
     * Get file storages
     * Returns array of all file storages
     *
     * @param integer $limit
     * @return QueryResultInterface
     * @todo: search inside file-storage/folder only
     */
    public function getFileStorages($limit = 1000) {
        $fileStorages = null;
        $rows = $this->getDatabaseConnection()->exec_SELECTgetRows(
            'uid, name, is_online',
            'sys_file_storage',
            'deleted = 0',
            '',
            'uid',
            $limit,
            'uid'
        );
        if (!empty($rows)) {
            $fileStorages = $rows;
        }
        return $fileStorages;
    }

    /**
     * Get sys_file_references
     * Returns array of file references by file resource uid
     *
     * @param File $file
     * @param integer $limit
     * @return QueryResultInterface
     */
    public function getSysFileReferences(File $file, $limit = 10000) {
        $fileReferences = null;
        if ($file instanceof File) {
            $addWhereArray['sysFile'] = 'uid_local = ' . $file->getUid();
            $addWhere = ' AND ' . implode(' AND ', $addWhereArray);
            $rows = $this->getDatabaseConnection()->exec_SELECTgetRows(
                'uid, pid, uid_local, uid_foreign, tablenames, fieldname',
                'sys_file_reference',
                '1=1' . $addWhere,
                '',
                '',
                $limit,
                'uid'
            );
            if (!empty($rows)) {
                $fileReferences = $rows;
            }
        }
        return $fileReferences;
    }

    /**
     * Update sys_file
     * Including updateRefIndex
     *
     * @param integer $sysFileUid
     * @param array $updateFieldsArray
     * @param bool $updateRefIndex Default = true
     * @return bool
     */
    public function updateSysFile($sysFileUid, $updateFieldsArray, $updateRefIndex = true)
    {
        $updateResult = false;
        if (intval($sysFileUid) && !empty($updateFieldsArray)) {
            $updateResult = $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
                'sys_file',
                'uid = ' . intval($sysFileUid),
                $updateFieldsArray
            );
            if ($updateResult) {
                if ($updateRefIndex) {
                    /** @var UpdateUtility $updateUtility */
                    $updateUtility = GeneralUtility::makeInstance(UpdateUtility::class);
                    $updateUtility->updateRefIndex('sys_file', intval($sysFileUid));
                }
            } else {
                # failed 1
            }
        } else {
            # failed 2
        }
        return $updateResult;
    }

    /**
     * Update sys_file_reference
     * Including updateRefIndex
     *
     * @param integer $sysFileReferenceUid
     * @param array $updateFieldsArray
     * @param bool $updateRefIndex Default = true
     * @return bool
     */
    public function updateSysFileReference($sysFileReferenceUid, $updateFieldsArray, $updateRefIndex = true)
    {
        $updateResult = false;
        if (intval($sysFileReferenceUid) && !empty($updateFieldsArray)) {
            $updateResult = $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
                'sys_file_reference',
                'uid = ' . intval($sysFileReferenceUid),
                $updateFieldsArray
            );
            if ($updateResult) {
                if ($updateRefIndex) {
                    /** @var UpdateUtility $updateUtility */
                    $updateUtility = GeneralUtility::makeInstance(UpdateUtility::class);
                    // @todo: ensure refIndex for sys_file_reference has been updated.
                    // SELECT * FROM `sys_refindex` WHERE `tablename` LIKE 'sys_file_reference' AND recuid = $sysFileReferenceUid;
                    $updateUtility->updateRefIndex('sys_file_reference', intval($sysFileReferenceUid));
                }
            } else {
                # failed 1
            }
        } else {
            # failed 2
        }
        return $updateResult;
    }

    /**
     * Get file object
     * 
     * @param integer $sysFileUid
     * @return null\File $fileObject
     */
    public function getFileObject($sysFileUid = null)
    {
        $fileObject = null;
        if (intval($sysFileUid)) {
            $fileObject = $this->factory->getFileObject(intval($sysFileUid));
        }
        return $fileObject;
    }
}
