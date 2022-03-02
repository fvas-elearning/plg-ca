<?php
namespace Ca\Form;

use Tk\Form;
use Tk\Form\Event;
use Tk\Form\Field;
use Tk\Str;

/**
 * Example:
 * <code>
 *   $form = new Assessment::create();
 *   $form->setModel($obj);
 *   $formTemplate = $form->getRenderer()->show();
 *   $template->appendTemplate('form', $formTemplate);
 * </code>
 * 
 * @author Mick Mifsud
 * @created 2019-10-31
 * @link http://tropotek.com.au/
 * @license Copyright 2019 Tropotek
 */
class Assessment extends \Uni\FormIface
{

    /**
     * @throws \Exception
     */
    public function init()
    {
        $layout = $this->getRenderer()->getLayout();
        $layout->removeRow('name', 'col-md-6');
        $layout->removeRow('assessorGroup', 'col-md-6');
        $layout->removeRow('includeZero', 'col-md-6');
        $layout->removeRow('icon', 'col-md-6');

        $tab = 'Details';

        $this->appendField(new Field\Input('name'))->setTabGroup($tab);
        $this->appendField(new Field\Select('assessorGroup', \Ca\Db\Assessment::getAssessorGroupList($this->getAssessment()->getAssessorGroup())))
            ->setTabGroup($tab)->setNotes('Who is the user group to submit the assessment evaluations.');

        $list = array('fa fa-user', 'fa fa-user-o', 'fa fa-user-md', 'fa fa-user-secret', 'fa fa-user-circle-o', 'fa fa-group',
            'fa fa-certificate', 'fa fa-ambulance', 'fa fa-vcard', 'fa fa-vcard-o', 'fa fa-handshake-o',
            'fa fa-building', 'fa fa-building-o', 'fa fa-industry', 'fa fa-institution', 'fa fa-hospital-o', 'fa fa-tree', 'fa fa-graduation-cap', 'fa fa-globe',
            'fa fa-trophy', 'fa fa-font-awesome', 'fa fa-heartbeat', 'fa fa-medkit', 'fa fa-heart', 'fa fa-truck', 'fa fa-ambulance');
        $this->appendField(new Field\Select('icon', Field\Select::arrayToSelectList($list, false)))
            ->setTabGroup($tab)->addCss('iconpicker')->setNotes('Select an identifying icon for this assessment');

//        $this->appendField(new Field\Checkbox('includeZero'))->setTabGroup($tab)
//            ->setCheckboxLabel('When calculating the score should 0 value results be included.');
        $this->appendField(new Field\Checkbox('enableCheckbox'))->setLabel('Subject Assessment Table')->setTabGroup($tab)
            ->setCheckboxLabel('Display a checkbox on the Subject student assessment table when Entry marked approved');

        // TODO: Hide this when assessor group is 'student'
        //$list = \App\Db\Placement::getStatusList();
        $list = array('Approved' => 'approved', 'Assessing' => 'assessing', 'Evaluating' => 'evaluating');
        $this->appendField(new Field\CheckboxGroup('placementStatus[]', $list))
            ->addCss('_tk-dual-select')->setTabGroup($tab)
            ->setNotes('Select the placement status values when assessments become available and can be submitted by users.');

//        $this->appendField(new Field\Select('placementStatus[]', $list))
//            ->addCss('tk-dual-select')->setTabGroup($tab)
//            ->setNotes('Select the placement status values when assessments become available and can be submitted by users.');

        $list = \App\Db\PlacementTypeMap::create()->findFiltered(array('courseId' => $this->getAssessment()->getCourseId()));
        $ptiField = $this->appendField(new Field\CheckboxGroup('placementTypeId[]', $list))
            ->addCss('_tk-dual-select')->setTabGroup($tab)
            ->setNotes('Enable this assessment for the selected placement types.');

//        $ptiField = $this->appendField(new Field\Select('placementTypeId[]', $list))->setTabGroup($tab)
//            ->addCss('tk-dual-select')->setAttr('data-title', 'Placement Types')
//            ->setNotes('Enable this assessment for the selected placement types.');

        $list = \Ca\Db\AssessmentMap::create()->findPlacementTypes($this->getAssessment()->getId());
        $ptiField->setValue($list);

        $tab = 'Reminder Notifications';

        $this->appendField(new Field\Checkbox('enableReminder'))->setLabel('')->setTabGroup($tab)
            ->addCss('tk-input-toggle')
            ->setCheckboxLabel('Enable Reminder Notifications');
        $this->appendField(new Field\Input('reminderInitialDays'))->setTabGroup($tab)
            ->setNotes('The number of days to send the first reminder after the placement end date.');
        $this->appendField(new Field\Input('reminderRepeatDays'))->setTabGroup($tab)
            ->setNotes('The number of days to send subsequent reminders after the initial date has passed.');
        $this->appendField(new Field\Input('reminderRepeatCycles'))->setTabGroup($tab)
            ->setNotes('The number of times to send subsequent reminders');

        $tab = 'Instructions';

        $this->appendField(new Field\Textarea('description'))->setLabel('Instructions')->setTabGroup($tab)
            ->setNotes('Enter any student instructions on how to complete placement entries.')
            ->addCss('mce')->setAttr('data-elfinder-path', $this->getConfig()->getInstitution()->getDataPath().'/media');;

        $this->appendField(new Event\Submit('update', array($this, 'doSubmit')));
        $this->appendField(new Event\Submit('save', array($this, 'doSubmit')));
        $this->appendField(new Event\Link('cancel', $this->getBackUrl()));

    }

    /**
     * @param \Tk\Request $request
     * @throws \Exception
     */
    public function execute($request = null)
    {
        $this->load(\Ca\Db\AssessmentMap::create()->unmapForm($this->getAssessment()));
        parent::execute($request);
    }

    /**
     * @param Form $form
     * @param Event\Iface $event
     * @throws \Exception
     */
    public function doSubmit($form, $event)
    {
        $originalKey = $this->getAssessment()->getNameKey();
        // Load the object with form data
        \Ca\Db\AssessmentMap::create()->mapForm($form->getValues(), $this->getAssessment());

        // Do Custom Validations
        $placemenTypeIds = $form->getFieldValue('placementTypeId');
        if(!$this->getAssessment()->isSelfAssessment() && (!is_array($placemenTypeIds) || !count($placemenTypeIds)) ) {
            $form->addFieldError('placementTypeId', 'Please select at least one placement type for this collection to be enabled for.');
        }

        $form->addFieldErrors($this->getAssessment()->validate());
        if ($form->hasErrors()) {
            return;
        }

        $this->getAssessment()->setDescription(Str::stripStyles($this->getAssessment()->getDescription()));

        $isNew = (bool)$this->getAssessment()->getId();
        $this->getAssessment()->save();

        // Do Custom data saving
        if ($originalKey != $this->getAssessment()->getNameKey()) {
            // Update all mail templates with the new tag
            $stm = $this->getConfig()->getDb()->prepare('UPDATE mail_template SET template = replace(template, ?, ?) WHERE course_id = ?;');
            $stm->execute(array('{'.$originalKey.'}', '{'.$this->getAssessment()->getNameKey().'}', $this->getAssessment()->getCourseId()));
            $stm->execute(array('{/'.$originalKey.'}', '{/'.$this->getAssessment()->getNameKey().'}', $this->getAssessment()->getCourseId()));
            $stm->execute(array('{'.$originalKey.'::', '{'.$this->getAssessment()->getNameKey().'::', $this->getAssessment()->getCourseId()));
        }

        \Ca\Db\AssessmentMap::create()->removePlacementType($this->getAssessment()->getId());
        if (is_array($placemenTypeIds) && count($placemenTypeIds)) {
            foreach ($placemenTypeIds as $placementTypeId) {
                \Ca\Db\AssessmentMap::create()->addPlacementType($this->getAssessment()->getId(), $placementTypeId);
            }
        }

        \Tk\Alert::addSuccess('Record saved!');
        $event->setRedirect($this->getBackUrl());
        if ($form->getTriggeredEvent()->getName() == 'save') {
            $event->setRedirect(\Tk\Uri::create()->set('assessmentId', $this->getAssessment()->getId()));
        }
    }

    /**
     * @return \Tk\Db\ModelInterface|\Ca\Db\Assessment
     */
    public function getAssessment()
    {
        return $this->getModel();
    }

    /**
     * @param \Ca\Db\Assessment $assessment
     * @return $this
     */
    public function setAssessment($assessment)
    {
        return $this->setModel($assessment);
    }
    
}