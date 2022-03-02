<?php
namespace Ca\Listener;

use App\Db\Placement;
use Ca\Db\Assessment;
use Ca\Db\Entry;
use Ca\Plugin;
use Dom\Template;
use Tk\ConfigTrait;
use Tk\Event\Subscriber;
use Tk\Log;

/**
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 */
class StudentAssessmentHandler implements Subscriber
{
    use ConfigTrait;

    /**
     * @param \Tk\Event\Event $event
     * @throws \Exception
     */
    public function showRow(\Tk\Event\Event $event)
    {
        /** @var \App\Ui\StudentAssessment $studentAssessment */
        $studentAssessment = $event->get('studentAssessment');
        if (!Plugin::getInstance()->isZonePluginEnabled(Plugin::ZONE_COURSE, $studentAssessment->getSubject()->getCourseId())) {
            return;
        }

        /** @var Placement $placement */
        $placement = $event->get('placement');
        // see if the placement check function is enabled in the course settings
        if (!$placement->getSubject()->getCourse()->getData()->get('placementCheck', '') || $this->getConfig()->getAuthUser()->isStudent()) return;
        if (
            !Entry::isPlacementCreditEqualAssessmentClass($placement, Assessment::ASSESSOR_GROUP_COMPANY) ||
            !Entry::isPlacementCreditEqualAssessmentClass($placement, Assessment::ASSESSOR_GROUP_STUDENT)
        ) {
            /** @var Template $row */
            $row = $event->get('rowTemplate');
            $row->prependHtml('companyName', ' <i class="fa fa-info-circle text-warning" title="Warning: Placement credit does not match assessment values. (Ignore if this is intended)."></i>');
        }

    }

    /**
     * @param \Tk\Event\Event $event
     * @throws \Exception
     */
    public function addCheckColumns(\Tk\Event\Event $event)
    {
        /** @var \App\Ui\StudentAssessment $studentAssessment */
        $studentAssessment = $event->get('studentAssessment');
        if (!Plugin::getInstance()->isZonePluginEnabled(Plugin::ZONE_COURSE, $studentAssessment->getSubject()->getCourseId())) {
            return;
        }
        $subject = $studentAssessment->getSubject();

        /** @var \App\Db\Placement $placement */
        foreach($studentAssessment->getPlacementList() as $placement) {
            $report = $placement->getReport();
            $list = \Ca\Db\AssessmentMap::create()->findFiltered(array(
                'subjectId' => $subject->getId(),
                'placementTypeId' => $placement->getPlacementTypeId(),
                'enableCheckbox' => true,
            ), \Tk\Db\Tool::create('FIELD(`name`, \'Self Assessment\') DESC'));
            foreach($list as $assessment) {
                /** @var \Ca\Db\Entry $entry */
                $entry = \Ca\Db\EntryMap::create()->findFiltered(array(
                    'assessmentId' => $assessment->getId(),
                    'placementId' => $placement->getId(),
                    'status' => array(\Ca\Db\Entry::STATUS_APPROVED, \Ca\Db\Entry::STATUS_PENDING, \Ca\Db\Entry::STATUS_AMEND)
                ))->current();
                $css = '';
                $status = '';
                if ($entry) {
                    $status = $entry->getStatus();
                    switch ($entry->getStatus()) {
                        case \Ca\Db\Entry::STATUS_PENDING:
                        case \Ca\Db\Entry::STATUS_AMEND:
                            $css = 'text-default';
                    }
                }
                $studentAssessment->addCheckColumn($assessment->getName(), $placement->getId(), ($entry != null), $css, $status);
            }
        }
    }

    /**
     * getSubscribedEvents
     * 
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            \App\UiEvents::STUDENT_ASSESSMENT_INIT => array(array('addCheckColumns', 0)),
            \App\UiEvents::STUDENT_ASSESSMENT_SHOW_ROW =>  array(array('showRow', 0)),
        );
    }

}


