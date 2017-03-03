<?php
namespace Jokumer\Xtools\Utility;

use TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools;
use TYPO3\CMS\Core\Database\ReferenceIndex;
use TYPO3\CMS\Core\Resource\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class UpdateUtility
 *
 * @package TYPO3
 * @subpackage tx_xtools
 * @author 2017 J.Kummer <typo3 et enobe dot de>, enobe.de
 * @copyright Copyright belongs to the respective authors
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class UpdateUtility
{

    /**
     * Sets PHP timeout to unlimited for execution
     *
     * @return void
     * @throws \Exception
     */
    public function setUnlimitedTimeout()
    {
        if (function_exists('set_time_limit')) {
            try {
                set_time_limit(0);
            } catch (\Exception $setMaxTimeOutExcpetion) {
            }
        }
    }

    /**
     * Backup DB tables
     *
     * @param array $tableNames
     * @param string $tableNameSuffix
     * @return void
     */
    public function backupDBTables($tableNames, $tableNameSuffix = '')
    {
        if (is_array($tableNames) && !empty($tableNames)) {
            if (strlen($tableNameSuffix) < 1) {
                $tableNameSuffix = '_' . $GLOBALS['EXEC_TIME'];
                // @todo check max length (64)
            }
            foreach ($tableNames as $tableName) {
                $createStatement = 'CREATE TABLE ' . $tableName . $tableNameSuffix . ' LIKE ' . $tableName . ';';
                $GLOBALS['TYPO3_DB']->sql_query($createStatement);
                $insertStatement = 'INSERT ' . $tableName . $tableNameSuffix . ' SELECT * FROM ' . $tableName . ';';
                $GLOBALS['TYPO3_DB']->sql_query($insertStatement);
            }
        }
    }

    /**
     * Get predefined pi_flexform with single field by key/value pair
     *
     * @param string $key
     * @param string $value
     * @param boolean $escapeValueAsCharacterData $value will be escaped with <![CDATA[]]> if not already exists
     * @return string
     */
    public function getPredefinedPiFlexformWithSingleFieldByKeyValuePair(
        $key,
        $value,
        $escapeValueAsCharacterData = false
    ) {
        $flexformData = '';
        if ($key && $value) {
            if ($escapeValueAsCharacterData) {
                $value = self::escapeValueAsCharacterData($value);
            }
            $flexformData = '<?xml version="1.0" encoding="utf-8" standalone="yes" ?>
<T3FlexForms>
    <data>
        <sheet index="sDEF">
            <language index="lDEF">
                <field index="' . $key . '">
                    <value index="vDEF">' . $value . '</value>
                </field>
            </language>
        </sheet>
    </data>
</T3FlexForms>';
        }
        return $flexformData;
    }

    /**
     * Get flexform XML by flexform data array
     *
     * @param array $flexformDataArray
     * @return string $flexformXML
     */
    public function getFlexformXmlByFlexformDataArray($flexformDataArray)
    {
        $flexformXml = null;
        if (is_array($flexformDataArray) && !empty($flexformDataArray)) {
            /** @var FlexFormTools $flexformTools */
            $flexformTools = GeneralUtility::makeInstance(FlexFormTools::class);
            $flexformXml = $flexformTools->flexArray2Xml($flexformDataArray, true);
        }
        return $flexformXml;
    }

    /**
     * Get flexformValue from flexformData by flexformPath
     *
     * @param string $flexformData
     * @param string $flexformFieldName
     * @param string $flexformPathPreprend
     * @param string $flexformPathAppend
     * @return mixed $flexformValue
     */
    public function getFlexformValueFromFlexformDataByFlexformFieldName(
        $flexformData,
        $flexformFieldName,
        $flexformPathPreprend = 'data/sDEF/lDEF',
        $flexformPathAppend = 'vDEF'
    ) {
        if ($flexformPathPreprend === null) {
            $flexformPathPreprend = 'data/sDEF/lDEF';
        }
        if ($flexformPathPreprend) {
            $flexformPathPreprend = $flexformPathPreprend . '/';
        }
        if ($flexformPathAppend === null) {
            $flexformPathAppend = 'vDEF';
        }
        if ($flexformPathAppend) {
            $flexformPathAppend = '/' . $flexformPathAppend;
        }
        $flexformPath = $flexformPathPreprend . $flexformFieldName . $flexformPathAppend;
        $flexformValue = null;
        if (isset($flexformData) && !empty($flexformData) && isset($flexformFieldName) && !empty($flexformFieldName)) {
            /** @var FlexFormTools $flexformTools */
            $flexformTools = GeneralUtility::makeInstance(FlexFormTools::class);
            $flexformValue = $flexformTools->getArrayValueByPath($flexformPath,
                GeneralUtility::xml2array($flexformData));
        }
        return $flexformValue;
    }

    /**
     * Get DAM uid by uid_foreign, table name and identifier
     *
     * @param integer $uidForeign
     * @param string $tableName
     * @param string $identifier
     * @return integer|null $uid
     */
    public function getDamUidByUidForeignTableNameAndIdentifier($uidForeign, $tableName, $identifier)
    {
        $uid = null;
        if (intval($uidForeign) && $tableName && $identifier) {
            $rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
                'uid_local',
                'tx_dam_mm_ref',
                'uid_foreign = ' . intval($uidForeign)
                . ' AND tablenames = \'' . $tableName . '\''
                . ' AND ident = \'' . $identifier . '\''
            );
            if (isset($rows[0]) && intval($rows['0']['uid_local'])) {
                $uid = intval($rows['0']['uid_local']);
            }
        }
        return $uid;
    }

    /**
     * Get sys_file uid by _migrateddamuid
     *
     * @param integer $migratedDamUid
     * @return integer|null $uid
     */
    public function getSysFileUidByMigratedDamUid($migratedDamUid)
    {
        $uid = null;
        if (intval($migratedDamUid)) {
            $rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
                'uid',
                'sys_file',
                '_migrateddamuid = ' . intval($migratedDamUid)
            );
            if (isset($rows[0]) && intval($rows['0']['uid'])) {
                $uid = intval($rows['0']['uid']);
            }
        }
        return $uid;
    }

    /**
     * Get sys_file uid of _migrateddamuid by uid_foreign, table name and identifier
     *
     * @param integer $uidForeign
     * @param string $tableName
     * @param string $identifier
     * @return integer|null $uid
     */
    public function getSysFileUidOfMigratedDamUidByUidForeignTableNameAndIdentifier(
        $uidForeign,
        $tableName,
        $identifier
    ) {
        $uid = null;
        if (intval($uidForeign) && $tableName && $identifier) {
            $rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
                'sys_file.uid',
                'sys_file'
                . ' LEFT JOIN tx_dam_mm_ref ON sys_file._migrateddamuid = tx_dam_mm_ref.uid_local',
                'uid_foreign = ' . intval($uidForeign)
                . ' AND tablenames = \'' . $tableName . '\''
                . ' AND ident = \'' . $identifier . '\''
            );
            if (isset($rows[0]) && intval($rows['0']['uid'])) {
                $uid = intval($rows['0']['uid']);
            }
        }
        return $uid;
    }

    /**
     * Get sys_file uid by identifier
     *
     * @param string $identifier
     * @return integer|null $uid
     */
    public function getSysFileUidByIdentifier($identifier)
    {
        $uid = null;
        if ($identifier) {
            $rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
                'sys_file.uid',
                'sys_file',
                'identifier = \'' . $identifier . '\''
            );
            if (isset($rows[0]) && intval($rows['0']['uid'])) {
                $uid = intval($rows['0']['uid']);
            }
        }
        return $uid;
    }

    /**
     * Create sys_file
     *
     * @param string $localFilePath The file on the server's hard disk to add
     * @param Folder $targetFolder The target folder where the file should be added
     * @param int $storageUid The uid if the storage where the file should be added
     * @param string $conflictMode a value of the \TYPO3\CMS\Core\Resource\DuplicationBehavior enumeration
     * @param bool $removeOriginal if set the original file will be removed after successful operation
     * @return File
     */
    public function createSysFile($localFilePath = '', $targetFolder = '', $storageUid = null, $conflictMode = DuplicationBehavior::RENAME, $removeOriginal = false)
    {
        $sysFile = null;
        if ($localFilePath && $targetFolder && $storageUid) {
            /** @var StorageRepository $storageRepository */
            $storageRepository = GeneralUtility::makeInstance(StorageRepository::class);
            /** @var ResourceStorage $storage */
            $storage = $storageRepository->findByUid($storageUid);
            /** @var Folder $targetFolderObject */
            $targetFolderObject = GeneralUtility::makeInstance(Folder::class, $storage, $targetFolder, '');
            /** @var File $sysFile */
            $sysFile = $storage->addFile($localFilePath, $targetFolderObject, '', $conflictMode, $removeOriginal);
        }
        return $sysFile;
    }

    /**
     * Rename sys_file
     *
     * @param FileInterface $file
     * @param string $targetFileName
     * @param int $storageUid The uid if the storage where the file should be renamed
     * @return FileInterface
     */
    public function renameSysFile($sysFile, $targetFileName, $storageUid = null)
    {
        /** @var StorageRepository $storageRepository */
        $storageRepository = GeneralUtility::makeInstance(StorageRepository::class);
        /** @var ResourceStorage $storage */
        $storage = $storageRepository->findByUid($storageUid);
        return $storage->renameFile($sysFile, $targetFileName);
    }

    /**
     * Create sys_file_reference and return insert uid on success
     *
     * @param integer $sysFileUid
     * @param string $tableName
     * @param string $fieldName
     * @param integer $uidForeign
     * @param integer $pid
     * @param array $additionalFields
     * @return integer|null
     */
    public function createSysFileReferenceAndReturnInsertUidOnSuccess(
        $sysFileUid,
        $tableName,
        $fieldName,
        $uidForeign,
        $pid,
        $additionalFields = array()
    ) {
        if (intval($sysFileUid)
            && $tableName
            && $fieldName
            && intval($uidForeign)
            && intval($pid)
        ) {
            $dataArray = array(
                'crdate' => $GLOBALS['EXEC_TIME'],
                'tstamp' => $GLOBALS['EXEC_TIME'],
                'uid_local' => $sysFileUid,
                'tablenames' => $tableName,
                'fieldname' => $fieldName,
                'uid_foreign' => $uidForeign,
                'table_local' => 'sys_file',
                // the sys_file_reference record should always placed on the same page
                // as the record to link to, see issue #46497
                'cruser_id' => 998,
                'pid' => $pid
            );
            if (isset($additionalFields['sys_language_uid']) && $additionalFields['sys_language_uid'] !== '') {
                $dataArray['sys_language_uid'] = $additionalFields['sys_language_uid'];
            }
            if (isset($additionalFields['hidden']) && $additionalFields['hidden'] !== '') {
                $dataArray['hidden'] = $additionalFields['hidden'];
            }
            if (isset($additionalFields['sorting_foreign']) && $additionalFields['sorting_foreign'] !== '') {
                $dataArray['sorting_foreign'] = $additionalFields['sorting_foreign'];
            }
            if (isset($additionalFields['title']) && $additionalFields['title'] !== '') {
                $dataArray['title'] = $additionalFields['title'];
            }
            if (isset($additionalFields['description']) && $additionalFields['description'] !== '') {
                $dataArray['description'] = $additionalFields['description'];
            }
            if (isset($additionalFields['alternative']) && $additionalFields['alternative'] !== '') {
                $dataArray['alternative'] = $additionalFields['alternative'];
            }
            if (isset($additionalFields['link']) && $additionalFields['link'] !== '') {
                $dataArray['link'] = $additionalFields['link'];
            }
            if (isset($additionalFields['downloadname']) && $additionalFields['downloadname'] !== '') {
                $dataArray['downloadname'] = $additionalFields['downloadname'];
            }
            $res = $GLOBALS['TYPO3_DB']->exec_INSERTquery('sys_file_reference', $dataArray);
            if ($res) {
                $sql_insert_id = $GLOBALS['TYPO3_DB']->sql_insert_id();
                self::updateRefIndex('sys_file_reference', $sql_insert_id);
                return $sql_insert_id;
            } else {
                return null;
            }
        } else {
            return null;
        }
    }

    /**
     * Escape value as character data <![CDATA[...]]>
     *
     * @param string $value
     * @return string $value
     */
    public function escapeValueAsCharacterData($value)
    {
        if ($value) {
            if (strpos(trim($value), '<![CDATA[') === false) {
                $value = '<![CDATA[' . $value . ']]>';
            }
        }
        return $value;
    }

    /**
     * Remove character data escape from value <![CDATA[...]]>
     *
     * @param string $value
     * @return string $value
     */
    public function removeCharacterDataEscapeFromValue($value)
    {
        if ($value) {
            if (strpos(trim($value), '<![CDATA[') !== false) {
                $value = preg_replace('#<!\[CDATA\[(.+?)\]\]>#s', '$1', $value);
            }
        }
        return $value;
    }

    /**
     * Update Reference index
     *
     * @param $tableName
     * @param $recordUid
     * @param bool $testOnly
     * @return array
     */
    public function updateRefIndex($tableName, $recordUid, $testOnly = false)
    {
        /** @var $refIndexObj ReferenceIndex */
        $refIndexObj = GeneralUtility::makeInstance(ReferenceIndex::class);
        $result = $refIndexObj->updateRefIndexTable($tableName, $recordUid, $testOnly);
        return $result;
    }

    /**
     * Update tt_content entry
     * Including updateRefIndex
     *
     * @param integer $ttContentEntryUid
     * @param array $updateFieldsArray
     * @param boolean $updateRefIndex Default = true
     * @return boolean
     */
    public function updateTtContentEntry($ttContentEntryUid, $updateFieldsArray, $updateRefIndex = true)
    {
        $updateResult = false;
        if (intval($ttContentEntryUid) && !empty($updateFieldsArray)) {
            $updateResult = $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
                'tt_content',
                'uid = ' . intval($ttContentEntryUid),
                $updateFieldsArray
            );
            if ($updateResult) {
                if ($updateRefIndex) {
                    self::updateRefIndex('tt_content', intval($ttContentEntryUid));
                }
                $resultDebug[__FUNCTION__]['success'][] = $ttContentEntryUid;
            } else {
                # failed 1
            }
        } else {
            # failed 2
        }
        return $updateResult;
    }

    /**
     * Update tt_content entries
     *
     * @param array $ttContentEntryUids
     * @param array $updateFieldsArray
     * @return boolean
     */
    public function updateTtContentEntries($ttContentEntryUids, $updateFieldsArray)
    {
        $updateResult = false;
        if (!empty($ttContentEntryUids) && !empty($updateFieldsArray)) {
            $ttContentEntryUidList = implode(',', $ttContentEntryUids);
            $updateResult = $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
                'tt_content',
                'uid IN (' . $ttContentEntryUidList . ')',
                $updateFieldsArray
            );
        } else {
            # failed
        }
        return $updateResult;
    }

    /**
     * Get single sys_file_collection by migrated DAM category
     *
     * @param string $damCategoryUidList commaseparated list of uids from tx_dam_cat
     * @return string|null $sysFileCollectionUidList commaseparated list of uids
     */
    public function getSysFileCollectionUidListByMigratedDamCategoryUidList($damCategoryUidList)
    {
        if ($damCategoryUidList) {
            $rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
                'uid',
                'sys_file_collection',
                '_migrateddamcatuid IN (' . $damCategoryUidList . ')'
            );
            if (!empty($rows)) {
                $sysFileCollectionUidArray = array();
                foreach ($rows as $row) {
                    $sysFileCollectionUidArray[] = intval($row['uid']);
                }
                $sysFileCollectionUidList = implode(',', $sysFileCollectionUidArray);
                return $sysFileCollectionUidList;
            } else {
                return null;
            }
        } else {
            return null;
        }
    }

    /**
     * Get translated entry uid by default language entry uid and target sys_language_uid from any table
     *
     * @param integer $defaultLanguageEntryUid
     * @param integer $targetSysLanguageUid
     * @param string $tableName
     * @return mixed integer|null
     */
    public function getTranslatedEntryUidByDefaultLanguageEntryUidAndTargetSysLanguageUidFromeAnyTable(
        $defaultLanguageEntryUid,
        $targetSysLanguageUid,
        $tableName
    ) {
        $translatedEntryUid = null;
        if (intval($defaultLanguageEntryUid) && intval($targetSysLanguageUid) && $tableName) {
            if ($tableName === 'tt_content') {
                $rows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
                    $tableName . '.uid',
                    $tableName,
                    'l18n_parent = ' . intval($defaultLanguageEntryUid) . ' AND sys_language_uid = ' . intval($targetSysLanguageUid)
                );
            }
            if (isset($rows[0]) && intval($rows['0']['uid'])) {
                $translatedEntryUid = intval($rows['0']['uid']);
            }
        }
        return $translatedEntryUid;
    }
    
    /**
     * Create directory
     *
     * @param $path
     * @return void
     */
    public function createDirectory($path)
    {
        if (!is_dir($path)) {
            try {
                GeneralUtility::mkdir_deep($path);
            } catch (\Exception $e) {
                echo 'Unable to create directory for images: ' . $path;
                exit;
            }
        }
    }

    /**
     * Sanitize the input file name against the 'control characters'.
     *
     * File names are not allowed to contain control characters [[:cntrl:]]
     * If so, file name will be hard rewritten
     * 
     * In rare cases, file names with umlauts contains control characters instead of real utf8 characters. Fx:
     *    Björkebåden_10WK.jpg
     * 
     * PHP rawurlencode() make wrong control characters visible. If rawurlencoded will look like below, all is fine
     *    Bj%C3%B6rkeb%C3%A5den_10WK.jpg
     * 
     * If rawurlencoded will look like the wrong below, than file name is affected for control characters
     *    Bjo%CC%88rkeba%CC%8Aden_10WK.jpg
     *
     * Only in this case, file name will be changed and gets removed all wrong chars, and will look like the result below
     *    Bjorkebaden_10WK.jpg 
     *
     * @param string $fileName File name to sanitize
     * @return bool
     */
    public function sanitizeFileNameAgainstControlCharacter($fileName)
    {
        $fileNameIsAffectedForControlCharacter = self::verifyFileNameIsAffectedForControlCharacter($fileName);
        if ($fileNameIsAffectedForControlCharacter) {
            $fileName = preg_replace('/[^a-zA-Z0-9\-\._]/','', $fileName);
        }
        return $fileName;
    }

    /**
     * Verifies file name if it is affected for containing 'control characters'.
     * In GeneralUtility::verifyFileNameAgainstDenyPattern($fileName) file names are not allowed to contain control characters
     * Therefore this checks for [[:cntrl:]] and should be sanitized before any further file handling
     *
     * @param string $fileName File path to verify
     * @return bool TRUE if AFFECTED
     */
    public function verifyFileNameIsAffectedForControlCharacter($fileName)
    {
        $pattern = '/(?:[[:cntrl:]])/i';
        return preg_match($pattern, $fileName);
    }

    /**
     * Sanitize file name and create sanitized duplicate if necessary
     *
     * @param string $fileName
     * @param string $filePath
     * @param bool $createSanitizedDuplicate Default false, to avoid mistakes ...
     * @return string $sanitizedFileName
     */
    public function sanitizeFileNameAndCreateSanitizedDuplicateIfNecessary($fileName, $filePath, $createSanitizedDuplicate = false)
    {
        $sanitizedFileName = self::sanitizeFileNameAgainstControlCharacter($fileName);
        if ((string)$sanitizedFileName !== '' && $createSanitizedDuplicate) {
            $sourceFile = $filePath . $fileName;
            $duplicatedSanitizedFileName = self::copyFile($sourceFile, $sanitizedFileName, $filePath, true);
            if ($duplicatedSanitizedFileName) {
                $sanitizedFileName = $duplicatedSanitizedFileName;
            }
        }
        return $sanitizedFileName;
    }

    /**
     * Copy file
     * No sanitzation, needs to be sanitized before use of this.
     *
     * @param string $sourceFile
     * @param string $destinationFileName
     * @param string $destinationFilePath
     * @param bool $renameFileNameIfDestinationAlreadyExists
     * @return string $destinationFileName
     */
    public function copyFile($sourceFile = '', $destinationFileName = '', $destinationFilePath = '', $renameIfDestinationAlreadyExists = false)
    {
        if ((string)$sourceFile !== '' && (string)$destinationFileName !== '' && (string)$destinationFilePath !== '' ) {
            $destinationFile = $destinationFilePath . $destinationFileName;
            if (is_file($sourceFile)) {
                if (is_file($destinationFile)) {
                    if ($renameIfDestinationAlreadyExists) {
                        $renamedDestinationFileName = self::getUniqueName($destinationFileName, $destinationFilePath);
                        $renamedDestinationFile = $destinationFilePath . $renamedDestinationFileName;
                        @copy($sourceFile, $renamedDestinationFile);
                        GeneralUtility::fixPermissions($renamedDestinationFile);
                        if (!is_file($renamedDestinationFile)) {
                            // Something went wrong
                            $destinationFileName = null;
                        } else {
                            // New destionation file name
                            $destinationFileName = $renamedDestinationFileName;
                        }
                    } else {
                        // Already exists
                    }
                } else {
                    @copy($sourceFile, $destinationFile);
                    GeneralUtility::fixPermissions($destinationFile);
                    if (!is_file($destinationFile)) {
                        // Something went wrong
                        $destinationFileName = null;
                    }
                }
            }
        }
        return $destinationFileName;
    }

    /**
     * Returns the destination path/filename of a unique filename/foldername in that path.
     * If $theFile exists in $theDest (directory) the file have numbers appended up to $this->maxNumber. Hereafter a unique string will be appended.
     * This function is used by fx. TCEmain when files are attached to records and needs to be uniquely named in the uploads/* folders
     *
     * @param string The input filename to check
     * @param string The directory for which to return a unique filename for $theFile. $theDest MUST be a valid directory. Should be absolute.
     * @param bool If set the filename is returned with the path prepended without checking whether it already existed!
     * @return string The destination absolute filepath (not just the name!) of a unique filename/foldername in that path.
     * @see \TYPO3\CMS\Core\DataHandling\DataHandler::checkValue()
     */
    public function getUniqueName($theFile, $theDest, $dontCheckForUnique = 0)
    {
        //This number decides the highest allowed appended number used on a filename before we use naming with unique strings
        $maxNumber = 99;
        //This number decides how many characters out of a unique MD5-hash that is appended to a filename if getUniqueName is asked to find an available filename.
        $uniquePrecision = 6;
        #$theDest = $this->is_directory($theDest);
        // $theDest is cleaned up
        $origFileInfo = GeneralUtility::split_fileref($theFile);
        // Fetches info about path, name, extension of $theFile
        if ($theDest) {
            #if ($this->getUniqueNamePrefix) {
            #    // Adds prefix
            #    $origFileInfo['file'] = $this->getUniqueNamePrefix . $origFileInfo['file'];
            #    $origFileInfo['filebody'] = $this->getUniqueNamePrefix . $origFileInfo['filebody'];
            #}
            // Check if the file exists and if not - return the filename...
            $fileInfo = $origFileInfo;
            $theDestFile = $theDest . '' . $fileInfo['file'];
            // The destinations file
            if (!file_exists($theDestFile) || $dontCheckForUnique) {
                // If the file does NOT exist we return this filename
                return $theFile;
            }
            // Well the filename in its pure form existed. Now we try to append numbers / unique-strings and see if we can find an available filename...
            $theTempFileBody = preg_replace('/_[0-9][0-9]$/', '', $origFileInfo['filebody']);
            // This removes _xx if appended to the file
            $theOrigExt = $origFileInfo['realFileext'] ? '.' . $origFileInfo['realFileext'] : '';
            for ($a = 1; $a <= $maxNumber + 1; $a++) {
                if ($a <= $maxNumber) {
                    // First we try to append numbers
                    $insert = '_' . sprintf('%02d', $a);
                } else {
                    // .. then we try unique-strings...
                    $insert = '_' . substr(md5(uniqid('', true)), 0, $uniquePrecision);
                }
                $theTestFile = $theTempFileBody . $insert . $theOrigExt;
                $theDestFile = $theDest . '' . $theTestFile;
                // The destinations file
                if (!file_exists($theDestFile)) {
                    // If the file does NOT exist we return this filename
                    return $theTestFile;
                }
            }
        }
    }

    /**
     * Moves a file
     *
     * @param string $source
     * @param string $target
     * @return bool $result
     * @throws \RuntimeException
     */
    public function moveFile($source, $target)
    {
        $result = false;
        try {
            $result = rename($source, $target);
        } catch (\Exception $result) {
            if ($result === false) {
                // @todo: handle errors, wrong charsets in file path would not work...
                $message = 'Moving file ' . $source . ' to ' . $target . ' failed.';
                $this->logMessage($message, 2);
                #throw new \RuntimeException($message, 1488329692);
            }
        }
        return $result;
    }

    /**
     * Log message
     * Write sys_log using \TYPO3\CMS\Core\Utility\GeneralUtility::sysLog
     *
     * @param string $message
     * @param integer $type Denotes which module that has submitted the entry. See "TYPO3 Core API". Use "4" for extensions.
     * @param integer $action Denotes which specific operation that wrote the entry. Use "0" when no sub-categorizing applies
     * @param integer $error Flag. 0 = message, 1 = error (user problem), 2 = System Error (which should not happen), 3 = security notice (admin)
     * @param integer $details_nr The message number. Specific for each $type and $action. This will make it possible to translate errormessages to other languages
     * @param string $details Default text that follows the message (in english!). Possibly translated by identification through type/action/details_nr
     * @param array $data Data that follows the log. Might be used to carry special information. If an array the first 5 entries (0-4) will be sprintf'ed with the details-text
     * @return void
     */
    public function logMessage($message = null, $type = 4, $action = 0, $error = 0, $details_nr = 0, array $data = null) {
        if ($message !== null) {
            // $GLOBALS['BE_USER']->simplelog($message, 'tx_xtools', $type);
            $GLOBALS['BE_USER']->writelog($type, $action, $error, $details_nr, 'tx_xtools' . $message, $data);
        }
    }
}
