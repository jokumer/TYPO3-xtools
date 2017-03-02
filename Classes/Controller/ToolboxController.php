<?php
namespace Jokumer\Xtools\Controller;

use Jokumer\Xtools\Controller\AbstractController;

/**
 * Class ToolboxController
 *
 * @package TYPO3
 * @subpackage tx_xtools
 * @author 2017 J.Kummer <typo3 et enobe dot de>, enobe.de
 * @copyright Copyright belongs to the respective authors
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class ToolboxController extends AbstractController
{
    /**
     * Selection
     *
     * @var array
     */
    protected $selection = [];

    /**
     * Initialize action
     *
     * @return void
     */
    protected function initializeAction()
    {
        parent::initializeAction();
        if ($this->request->hasArgument('selection')) {
            $this->selection = $this->request->getArgument('selection');
        }
    }
    
    /**
     * Index action
     *
     * @return void
     */
    public function indexAction()
    {
        
    }

    /**
     * Backup
     *
     * @return void
     */
    public function backupAction()
    {
        $executed = [];
        if ($this->request->hasArgument('execute')) {
            if ($this->request->getArgument('execute')) {
                if (isset($this->selection['tables'])) {
                    $initiator = (isset($this->selection['initiator']['controller'])) ? $this->selection['initiator']['controller'] : '';
                    $this->backupDBTables($this->selection['tables'], $initiator);
                    $executed['tables'] = $this->selection['tables'];
                    $executed['initiator'] = $initiator;
                }
            }
        }
        $this->data['executed'] = $executed;
        $this->data['selection'] = $this->selection;
        $this->view->assign('data', $this->data);
    }

    /**
     * Backup db tables
     *
     * @param array $tables
     * @param string $initiator
     * @return void
     */
    private function backupDBTables(array $tables, $initiator = '')
    {
        if (is_array($tables)) {
            $tableNameSuffix = '_bak_xtools_' . $initiator . '_' . $GLOBALS['EXEC_TIME'];
            $this->updateUtility->backupDBTables($tables, $tableNameSuffix);
        }
    }
}
