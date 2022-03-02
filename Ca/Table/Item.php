<?php
namespace Ca\Table;

use Tk\Form\Field;
use Tk\Table\Cell;

/**
 * Example:
 * <code>
 *   $table = new Item::create();
 *   $table->init();
 *   $list = ObjectMap::getObjectListing();
 *   $table->setList($list);
 *   $tableTemplate = $table->show();
 *   $template->appendTemplate($tableTemplate);
 * </code>
 * 
 * @author Mick Mifsud
 * @created 2019-11-05
 * @link http://tropotek.com.au/
 * @license Copyright 2019 Tropotek
 */
class Item extends \Uni\TableIface
{
    
    /**
     * @return $this
     * @throws \Exception
     */
    public function init()
    {

        $this->appendCell(new Cell\OrderBy('orderBy'))->setIconOnly();
        $this->appendCell(new Cell\Checkbox('id'));
        $this->appendCell(new Cell\Text('#'))->addOnPropertyValue(function ($cell, $obj, $value) {
            /* @var $cell Cell\Text */
            /* @var $obj \Ca\Db\Item */
            $value = $cell->getRow()->getRowId()+1;
            return $value;
        });
        //$this->appendCell(new Cell\Text('uid'));
        //$this->appendCell(new Cell\Text('assessmentId'));
        $this->appendCell(new Cell\Text('name'))->addCss('key')->setUrl($this->getEditUrl())
            ->addOnPropertyValue(function ($cell, $obj, $value) {
                /* @var $cell Cell\Text */
                /* @var $obj \Ca\Db\Item */
                if (!$value) {
                    $value = 'N/A';
                    /** @var \Ca\Db\Competency $comptetency */
                    $list = \Ca\Db\CompetencyMap::create()->findFiltered(array('itemId' => $obj->getId()));
                    if ($list->count()) {
                        $comptetency = $list->current();
                        if ($comptetency && $comptetency->getName())
                            $value = $comptetency->getName();
                    }
                }
                return $value;
            })
            ->addOnCellHtml(function ($cell, $obj, $html) {
                /* @var $cell Cell\Text */
                /* @var $obj \Ca\Db\Item */
                $list = \Ca\Db\CompetencyMap::create()->findFiltered(array('itemId' => $obj->getId()));
                $listHtml = '';
                if ($list->count() > 1) {
                    $listHtml = '<ul>';
                    foreach ($list as $comp) {
                        if ($cell->getUrl()) {
                            $listHtml .= sprintf('<li><a href="%s">%s</a></li>', htmlspecialchars($cell->getCellUrl($obj)), $comp->getName());
                        } else {
                            $listHtml .= sprintf('<li>%s</li>', $comp->getName());
                        }
                    }
                    $listHtml .= '</ul>';
                    if (!$obj->getName()) return $listHtml;
                }
                return $html . $listHtml;
            });

        $this->appendCell(new Cell\Text('domainId'))->addOnPropertyValue(function ($cell, $obj, $value) {
            /* @var $cell Cell\Text */
            /* @var $obj \Ca\Db\Item */
            $value = '[Assessment]';
            $domain = \Ca\Db\DomainMap::create()->find($obj->getDomainId());
            if ($domain) $value = $domain->getName();
            return $value;
        });

        $this->appendCell(new Cell\Text('scaleId'))->addOnPropertyValue(function ($cell, $obj, $value) {
            /* @var $cell Cell\Text */
            /* @var $obj \Ca\Db\Item */
            $scale = \Ca\Db\ScaleMap::create()->find($obj->getScaleId());
            if ($scale) $value = $scale->getName();
            return $value;
        });
        $this->appendCell(new Cell\Boolean('gradable'));
        $this->appendCell(new Cell\Boolean('required'));
        $this->appendCell(new Cell\Date('modified'));
        $this->appendCell(new Cell\Date('created'));

        // Filters
        $this->appendFilter(new Field\Input('keywords'))->setAttr('placeholder', 'Search');

        // Actions
        //$this->appendAction(\Tk\Table\Action\Link::createLink('New Item', \Bs\Uri::createHomeUrl('/ca/itemEdit.html'), 'fa fa-plus'));
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
     * @return \Tk\Db\Map\ArrayObject|\Ca\Db\Item[]
     * @throws \Exception
     */
    public function findList($filter = array(), $tool = null)
    {
        if (!$tool) $tool = $this->getTool('order_by', 50);
        $filter = array_merge($this->getFilterValues(), $filter);
        $list = \Ca\Db\ItemMap::create()->findFiltered($filter, $tool);
        return $list;
    }

}