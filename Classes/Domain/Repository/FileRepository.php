<?php
namespace Jokumer\Xtools\Domain\Repository;

use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\ResourceStorage;

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
    public function getFilesDuplications($storage = null, $directory = null, $limit = 1000) {
        $filesDuplications = null;
        if ($storage instanceof ResourceStorage) {
            $addWhereArray['storage'] = 'storage = ' . $storage->getUid();
            if ($directory !== null) {
                $addWhereArray['storage'] = 'identifier LIKE \'' . $directory . '%\'';
            }
            $addWhereArray['excludeProcessedFiles'] = '(identifier NOT LIKE \'%/_processed_/%\' AND pid = 0)';
            $addWhere = ' AND ' . implode(' AND ', $addWhereArray);
            $rows = $this->getDatabaseConnection()->exec_SELECTgetRows(
                'uid, sha1, name, type, count(uid) totalCount',
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
    public function getFileDuplications($storage = null, $directory = null, $sha1 = '', $limit = 1000) {
        $fileDuplications = null;
        if ($storage instanceof ResourceStorage) {
            $addWhereArray['storage'] = 'sf.storage = ' . $storage->getUid();
            if ($directory !== null) {
                $addWhereArray['storage'] = 'sf.identifier LIKE \'' . $directory . '%\'';
            }
            $addWhereArray['excludeProcessedFiles'] = '(sf.identifier NOT LIKE \'%/_processed_/%\' AND sf.pid = 0)';
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
                            $fileDuplications[$key]['fileObject'] = $this->factory->getFileObject($row['uid']);
                            $fileDuplications[$key]['usage'] = $row['sfrCount'];
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
     * Get file references
     * Returns array of file references by file resource uid
     *
     * @param File $file
     * @param ResourceStorage $storageUid
     * @param string $directory
     * @param integer $limit
     * @return QueryResultInterface
     */
    public function getFileReferences(File $file, $storage = null, $directory = null, $limit = 1000) {
        $fileReferences = null;
        if ($file instanceof File) {
            // @todo: add where clause (not deleted, ....)
            $addWhere = '';
            $rows = $this->getDatabaseConnection()->exec_SELECTgetRows(
                '*',
                'sys_file_reference',
                '1=1' . $addWhere,
                'uid_local = ' . $file->getUid(),
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
}
