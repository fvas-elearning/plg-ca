<?php
namespace Ca\Table;

use Tk\Form\Field;
use Tk\Table\Cell;

/**
 * Example:
 * <code>
 *   $table = new Assessment::create();
 *   $table->init();
 *   $list = ObjectMap::getObjectListing();
 *   $table->setList($list);
 *   $tableTemplate = $table->show();
 *   $template->appendTemplate($tableTemplate);
 * </code>
 * 
 * @author Mick Mifsud
 * @created 2019-10-31
 * @link http://tropotek.com.au/
 * @license Copyright 2019 Tropotek
 */
class Assessment extends \Uni\TableIface
{
    
    /**
     * @return $this
     * @throws \Exception
     */
    public function init()
    {
    
        $this->appendCell(new Cell\Checkbox('id'));
        //$this->appendCell(new Cell\Text('uid'));
        //$this->appendCell(new Cell\Text('courseId'));
        $this->appendCell(new Cell\Text('icon'))->addOnCellHtml(function ($cell, $obj, $html) {
            $ico = 'fa fa-file-o';
            if ($obj->getIcon())
                $ico = $obj->getIcon();
            return sprintf('<i class="%s"></i>', $ico);
        });
        $this->appendCell(new Cell\Text('name'))->addCss('key')->setUrl($this->getEditUrl());
        $this->appendCell(new Cell\Text('placementTypes'))->addOnPropertyValue(function ($cell, $obj, $value) {
            /* @var $obj \Ca\Db\Assessment */
            $list = $obj->getPlacementTypes();
            $value = '';
            foreach ($list as $placementType) {
                $value .= $placementType->name . ', ';
            }
            $value = trim($value, ', ');
            return $value;
        });
        $this->appendCell(new Cell\ArrayObject('placementStatus'));
        $this->appendCell(new Cell\Text('assessorGroup'));
        $this->appendCell(new Cell\Boolean('enableReminder'));
        //$this->appendCell(new Cell\Boolean('multiple'));
        //$this->appendCell(new Cell\Boolean('includeZero'));
        //$this->appendCell(new Cell\Date('publishResult'));

        $this->appendCell(new Cell\Date('modified'));
        $this->appendCell(new Cell\Date('created'));

        // Filters
        $this->appendFilter(new Field\Input('keywords'))->setAttr('placeholder', 'Search');

        // Actions
        //$this->appendAction(\Tk\Table\Action\Link::create('New Assessment', 'fa fa-plus', \Bs\Uri::createHomeUrl('/ca/assessmentEdit.html')));
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
     * @return \Tk\Db\Map\ArrayObject|\Ca\Db\Assessment[]
     * @throws \Exception
     */
    public function findList($filter = array(), $tool = null)
    {
        if (!$tool) $tool = $this->getTool();
        $filter = array_merge($this->getFilterValues(), $filter);
        $list = \Ca\Db\AssessmentMap::create()->findFiltered($filter, $tool);
        return $list;
    }

}