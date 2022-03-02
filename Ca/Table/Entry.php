<?php
namespace Ca\Table;

use Tk\Form\Field;
use Tk\Table\Cell;

/**
 * Example:
 * <code>
 *   $table = new Entry::create();
 *   $table->init();
 *   $list = ObjectMap::getObjectListing();
 *   $table->setList($list);
 *   $tableTemplate = $table->show();
 *   $template->appendTemplate($tableTemplate);
 * </code>
 * 
 * @author Mick Mifsud
 * @created 2019-11-06
 * @link http://tropotek.com.au/
 * @license Copyright 2019 Tropotek
 */
class Entry extends \Uni\TableIface
{

    
    /**
     * @return $this
     * @throws \Exception
     */
    public function init()
    {
    
        $this->appendCell(new Cell\Checkbox('id'));
        $this->appendCell(new Cell\Text('assessmentId'));
        $this->appendCell(new Cell\Text('subjectId'));
        $this->appendCell(new Cell\Text('studentId'));
        $this->appendCell(new Cell\Text('assessorId'));
        $this->appendCell(new Cell\Text('placementId'));
        $this->appendCell(new Cell\Text('title'))->addCss('key')->setUrl($this->getEditUrl());
        $this->appendCell(new Cell\Text('assessorName'));
        $this->appendCell(new Cell\Text('assessorEmail'));
        $this->appendCell(new Cell\Text('absent'));
        $this->appendCell(new Cell\Text('average'));
        $this->appendCell(new Cell\Text('status'));
        $this->appendCell(new Cell\Date('modified'));
        $this->appendCell(new Cell\Date('created'));

        // Filters
        $this->appendFilter(new Field\Input('keywords'))->setAttr('placeholder', 'Search');

        // Actions
        //$this->appendAction(\Tk\Table\Action\Link::createLink('New Entry', \Bs\Uri::createHomeUrl('/ca/entryEdit.html'), 'fa fa-plus'));
        //$this->appendAction(\Tk\Table\Action\ColumnSelect::create()->setUnselected(array('modified', 'created')));
        if ($this->getAuthUser()->isCoordinator()) {
            $this->appendAction(\Tk\Table\Action\Delete::create());
        }
        $this->appendAction(\Tk\Table\Action\Csv::create());

        // load table
        //$this->setList($this->findList());
        
        return $this;
    }

    /**
     * @param array $filter
     * @param null|\Tk\Db\Tool $tool
     * @return \Tk\Db\Map\ArrayObject|\Ca\Db\Entry[]
     * @throws \Exception
     */
    public function findList($filter = array(), $tool = null)
    {
        if (!$tool) $tool = $this->getTool();
        $filter = array_merge($this->getFilterValues(), $filter);
        $list = \Ca\Db\EntryMap::create()->findFiltered($filter, $tool);
        return $list;
    }

}