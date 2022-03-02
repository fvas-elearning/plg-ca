<?php
namespace Ca\Listener;

use Ca\Db\Assessment;
use Ca\Db\Entry;
use Ca\Plugin;
use Dom\Template;
use Tk\ConfigTrait;
use Tk\Event\Subscriber;

/**
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 */
class AssessmentUnitsHandler implements Subscriber
{
    use ConfigTrait;

    /**
     * @param \Tk\Event\Event $event
     * @throws \Exception
     */
    public function onInit(\Tk\Event\Event $event)
    {

        if (!class_exists('\\Rs\\Calculator')) return;
        /** @var \App\Ui\StudentAssessment $studentAssessment */
        $studentAssessment = $event->get('studentAssessment');// see if the placement check function is enabled in the course settings
        $subject = $studentAssessment->getSubject();
        if (!\Rs\Plugin::getInstance()->isZonePluginEnabled(Plugin::ZONE_COURSE, $subject->getCourseId())) return;
        if (!Plugin::getInstance()->isZonePluginEnabled(Plugin::ZONE_COURSE, $subject->getCourseId())) return;
        if (!$subject->getCourse()->getData()->get('placementCheck', '') || $this->getConfig()->getAuthUser()->isStudent()) return;


        /** @var \App\Db\Placement $placement */
        foreach($studentAssessment->getPlacementList() as $placement) {
            $companyTag = '<div class="cnr-r-red"></div>';
            $studentTag = '<div class="cnr-l-orange"></div>';
            $companyARule = null;
            $studentARule = null;

            if (!Entry::isPlacementCreditEqualAssessmentClass($placement, Assessment::ASSESSOR_GROUP_COMPANY)) {
                $companyARule = Entry::getPlacementAssessmentValueRuleObject($placement, Assessment::ASSESSOR_GROUP_COMPANY);
            }
            if (!Entry::isPlacementCreditEqualAssessmentClass($placement, Assessment::ASSESSOR_GROUP_STUDENT)) {
                $studentARule = Entry::getPlacementAssessmentValueRuleObject($placement, Assessment::ASSESSOR_GROUP_STUDENT);
            }
            $box = '<div class="cnr-box">%s%%s</div>';
            if ($companyARule && $studentARule) {
                if ($companyARule->getId() == $studentARule->getId()) {
                    // Both on the same column
                    $html = sprintf($box,$companyTag.$studentTag);
                    $studentAssessment->setUnitColumnHtml($companyARule->getLabel(), $placement->getId(), $html);
                } else {
                    $html = sprintf($box, $companyTag);
                    $studentAssessment->setUnitColumnHtml($companyARule->getLabel(), $placement->getId(), $html);
                    $html = sprintf($box, $studentTag);
                    $studentAssessment->setUnitColumnHtml($studentARule->getLabel(), $placement->getId(), $html);
                }
            } else {
                if ($companyARule) {
                    $html = sprintf($box, $companyTag);
                    $studentAssessment->setUnitColumnHtml($companyARule->getLabel(), $placement->getId(), $html);
                }
                if ($studentARule) {
                    $html = sprintf($box, $studentTag);
                    $studentAssessment->setUnitColumnHtml($studentARule->getLabel(), $placement->getId(), $html);
                }
            }

        }

        $html = <<<HTML
<p><span class="cnr-box-eg inCompany"><span class="cnr-r-red"></span></span> = Supervisor Assessment value that differs with the placement credit.</p>
<p><span class="cnr-box-eg inCompany"><span class="cnr-l-orange"></span></span> = Self-Assessment value that differs with the placement credit.</p>
HTML;
        $studentAssessment->appendInfoHtml($html);


    }

    /**
     * getSubscribedEvents
     * 
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            \App\UiEvents::STUDENT_ASSESSMENT_INIT => array('onInit', -100)
        );
    }

}


