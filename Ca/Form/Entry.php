<?php
namespace Ca\Form;

use Tk\Form;
use Tk\Form\Event;
use Tk\Form\Field;

/**
 * Example:
 * <code>
 *   $form = new Entry::create();
 *   $form->setModel($obj);
 *   $formTemplate = $form->getRenderer()->show();
 *   $template->appendTemplate('form', $formTemplate);
 * </code>
 * 
 * @author Mick Mifsud
 * @created 2019-11-06
 * @link http://tropotek.com.au/
 * @license Copyright 2019 Tropotek
 */
class Entry extends \Uni\FormIface
{
    const MODE_PRIVATE = 'private';         // For logged in users
    const MODE_PUBLIC = 'public';           // For public users

    protected $public = false;


    /**
     * @param bool $isPublic
     * @param string $formId
     */
    public function __construct($isPublic = false, $formId = '')
    {
        parent::__construct($formId);
        $this->setPublic($isPublic);
    }

    /**
     * @throws \Exception
     */
    public function init()
    {
        $this->addCss('ca-entry-edit');

        $fieldset = 'Entry Details';
        if ($this->getAuthUser() && $this->getAuthUser()->isStaff() && !$this->isPublic()) {
            $this->appendField(new Field\Input('title'))->setFieldset($fieldset);
        } else {
            $this->appendField(new Field\Html('title'))->setFieldset($fieldset)->setEscapeText(true);
        }
//        if (!$this->isPublic()) {
//            $avg = $this->getEntry()->getAverage();
//            $this->appendField(new Field\Html('average', sprintf('%.2f', $this->getEntry()->getAverage())))->setFieldset($fieldset);
//        }
        if (!$this->isPublic() && $this->getAuthUser() && $this->getAuthUser()->isStaff()) {
            $this->appendField(new \App\Form\Field\StatusSelect('status', \Ca\Db\Entry::getStatusList($this->getEntry()->getStatus())))
                ->setRequired()->prependOption('-- Status --', '')->setNotes('Set the status. Use the checkbox to disable notification emails.')->setFieldset($fieldset);
        } else {
            $this->appendField(new \Tk\Form\Field\Html('status'))->setFieldset($fieldset);
        }

        // $this->appendField(new Field\Input('title'));        // HTML from auto gen title
        $this->appendField(new Field\Input('assessorName'))->setFieldset($fieldset)->setRequired();
        $this->appendField(new Field\Input('assessorEmail'))->setFieldset($fieldset);
        if ($this->getEntry()->getAssessorId() != $this->getEntry()->getStudentId()) {
            $this->appendField(new Field\Input('absent'))->setFieldset($fieldset)->setNotes('Enter the number of days the student was absent if any.');
        }
        $this->appendField(new Field\Textarea('notes'))->setLabel('Comments')->setFieldset($fieldset);

        $items = \Ca\Db\ItemMap::create()->findFiltered(array('assessmentId' => $this->getEntry()->getAssessmentId()),
            \Tk\Db\Tool::create('order_by'));

        $fieldset = '';
        /** @var \Ca\Db\Item $item */
        foreach ($items as $item) {
            if ($item->getDomain()) {
                if ($item->getDomain()->getName() != $fieldset)
                    $fieldset = $item->getDomain()->getName();
            } else {
                $fieldset = '';
            }
            if (!$fieldset) $fieldset = 'Conclusion';

            $field = \Ca\Form\Field\ItemHelper::createField($item);
            if (!$field) continue;
            $this->appendField($field)->setFieldset($fieldset, 'ca-row');

            $val = \Ca\Db\EntryMap::create()->findValue($this->getEntry()->getId(), $item->getId());
            if ($val) {
                $field->setValue($val->value);
            }
//            else if (!$this->getEntry()->getId() && $item->getScaleId() == 7) {
//            //} else if ($item->getScaleId() == 7) {    // Not sure if we should do it every time there is no value
//                if (class_exists('\\Rs\\Calculator')) {
//                    $placement = $this->getEntry()->getPlacement();
//                    $arr = ['small' => 1, 'prod' => 2, 'equine' => 3, 'other' => 4];
//                    /** @var \Rs\Db\Rule $rule */
//                    $rule = \Rs\Calculator::findPlacementRuleList($placement)->current();
//                    $field->setValue($arr[$rule->getLabel()]);
//                }
//            }
        }

        if ($this->isPublic()) {
            $this->appendField(new Event\Submit('submit', array($this, 'doSubmit')))->addCss('btn-success')->setIconRight('fa fa-arrow-right')->addCss('pull-right')->setLabel('Submit');
            $this->appendField(new Event\Link('cancel', \Uni\Uri::create('/index.html')));
        } else {
            $this->appendField(new Event\Submit('update', array($this, 'doSubmit')));
            $this->appendField(new Event\Submit('save', array($this, 'doSubmit')));
            $this->appendField(new Event\Link('cancel', $this->getBackUrl()));
        }

        $template = $this->getRenderer()->getTemplate();
        $template->appendCssUrl(\Tk\Uri::create('/plugin/plg-ca/assets/styles.less'));

        $js = <<<JS
jQuery(function ($) {
  if (config.roleType === 'staff') {
    $('.ca-entry-edit .tk-form-events').clone(true).appendTo($('.ca-entry-edit fieldset.EntryDetails'));
  }
});
JS;
        if (!$this->isPublic() && $this->getAuthUser() && $this->getAuthUser()->isStaff()) {
            $template->appendJs($js);
        }
    }

    /**
     * @param \Tk\Request $request
     * @throws \Exception
     */
    public function execute($request = null)
    {
        $this->load(\Ca\Db\EntryMap::create()->unmapForm($this->getEntry()));
        parent::execute($request);
    }

    /**
     * @param Form $form
     * @param Event\Iface $event
     * @throws \Exception
     */
    public function doSubmit($form, $event)
    {
        // Load the object with form data
        \Ca\Db\EntryMap::create()->mapForm($form->getValues(), $this->getEntry());

        $items = \Ca\Db\ItemMap::create()->findFiltered(array('assessmentId' => $this->getEntry()->getAssessmentId()),
            \Tk\Db\Tool::create('order_by'));
        foreach ($items as $item) {
            $name = 'item-'.$item->getId();
            if (!\Ca\Form\Field\ItemHelper::isValid($item, $form->getFieldValue($name))) {
                $form->addFieldError($name, 'Please enter a valid value for this item.');
            }
        }

        // Do Custom Validations
        $form->addFieldErrors($this->getEntry()->validate());
        if ($form->hasErrors()) {
            return;
        }

        if ($this->getEntry()->getId() < 0) {
            \Tk\Alert::addInfo('This form was successfully submitted and validated.<br/>However no data was saved as this is only a preview form. ;-)');
            return;
        }

        $isNew = (bool)$this->getEntry()->getId();

        if ($this->getAuthUser() && $this->getAuthUser()->isStudent() &&
            $this->getEntry()->getAssessment()->isSelfAssessment() && $this->getEntry()->hasStatus(\Ca\Db\Entry::STATUS_AMEND)) {
            $this->getEntry()->setStatus(\Ca\Db\Entry::STATUS_PENDING);
        }
        $this->getEntry()->setStatusNotify(true);
        $this->getEntry()->save();

        // Save Item values
        \Ca\Db\EntryMap::create()->removeValue($this->getEntry()->getVolatileId());
        foreach ($form->getValues('/^item\-/') as $name => $val) {
            $id = (int)substr($name, strrpos($name, '-') + 1);
            \Ca\Db\EntryMap::create()->saveValue($this->getEntry()->getVolatileId(), $id, $val);
        }

        $event->setRedirect($this->getBackUrl());
        if ($form->getTriggeredEvent()->getName() == 'save') {
            \Tk\Alert::addSuccess('Record saved!');
            $event->setRedirect(\Tk\Uri::create()->set('entryId', $this->getEntry()->getId()));
        } else if ($form->getTriggeredEvent()->getName() == 'update' && $this->getAuthUser()->isStaff()) {
            \Tk\Alert::addSuccess('Record saved!');
            $url = \Uni\Uri::createSubjectUrl('/placementEdit.html')->set('placementId', $this->getEntry()->getPlacementId());
            $event->setRedirect($url);
        }

        if (!$this->getAuthUser() || $this->getAuthUser()->isGuest()) {
            \Tk\Alert::addSuccess('Thank you! Student placement feedback submitted successfully.');
            $event->setRedirect(\Tk\Uri::create('/index.html'));
        }

    }

    /**
     * @param bool $b
     * @return Entry
     */
    public function setPublic($b = true)
    {
        $this->public = $b;
        return $this;
    }

    /**
     * @return bool
     */
    public function isPublic()
    {
        return $this->public;
    }

    /**
     * @return \Tk\Db\ModelInterface|\Ca\Db\Entry
     */
    public function getEntry()
    {
        return $this->getModel();
    }

    /**
     * @param \Ca\Db\Entry $entry
     * @return $this
     */
    public function setEntry($entry)
    {
        return $this->setModel($entry);
    }
    
}