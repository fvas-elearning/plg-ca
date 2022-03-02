<?php
namespace Ca\Listener;

use App\Db\Placement;
use Ca\Db\Assessment;
use Ca\Db\Entry;
use Dom\Template;
use Tk\Table\Cell\Text;
use Uni\Db\Permission;
use Symfony\Component\HttpKernel\KernelEvents;
use Tk\ConfigTrait;
use Tk\Event\Subscriber;

/**
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 * @todo; we still need to implement this
 */
class PlacementManagerHandler implements Subscriber
{
    use ConfigTrait;


    /**
     * @var null|\App\Controller\Placement\Manager
     */
    protected $controller = null;


    /**
     * PlacementManagerHandler constructor.
     */
    public function __construct()
    {
    }

    /**
     * @param \Symfony\Component\HttpKernel\Event\FilterControllerEvent $event
     */
    public function onControllerInit($event)
    {
        /** @var \App\Controller\Placement\Edit $controller */
        $controller = \Tk\Event\Event::findControllerObject($event);
        if ($controller instanceof \App\Controller\Placement\Manager) {
            $config = \Uni\Config::getInstance();
            $this->controller = $controller;
        }
    }

    /**
     * Check the user has access to this controller
     *
     * @param \Tk\Event\TableEvent $event
     * @throws \Exception
     */
    public function addActions(\Tk\Event\TableEvent $event)
    {
        if (!$event->getTable() instanceof \App\Table\Placement || !$this->controller) return;
        $subjectId = $event->getTable()->get('subjectId');
        if (!$subjectId) return;
        $assessmentList = \Ca\Db\AssessmentMap::create()->findFiltered(array(
            'subjectId' => $subjectId,
            'publish' => true
        ));

        /** @var \Tk\Table\Cell\ButtonCollection $actionsCell */
        $actionsCell = $event->getTable()->findCell('actions');
        $spec = '/ca/entryEdit.html';
        if ($event->getTable()->get('isMentorView', false) || !$this->getAuthUser()->isLearner())
            $spec = '/ca/entryView.html';

        $actionsCell->addOnCellHtml(function (\Tk\Table\Cell\Iface $cell, Placement $placement, $html) {
            if (!$placement->getSubject()->getCourse()->getData()->get('placementCheck', '') || $this->getConfig()->getAuthUser()->isStudent()) return;
            if (
                !Entry::isPlacementCreditEqualAssessmentClass($placement, Assessment::ASSESSOR_GROUP_COMPANY) ||
                !Entry::isPlacementCreditEqualAssessmentClass($placement, Assessment::ASSESSOR_GROUP_STUDENT)
            ) {
                // TODO: Need to make this nicer sometime in the future...
                $cell->getRow()->addCss('class-mismatch');
                $cell->getRow()->setAttr('style', 'background-color: #FFDFDF;cursor: help;');
                $cell->getRow()->setAttr('title', 'Warning: Supervisor assessment category does not match placement category.');
            }

        });

        /** @var \Ca\Db\Assessment $assessment */
        foreach ($assessmentList as $assessment) {
            $url = \Uni\Uri::createSubjectUrl($spec)->set('assessmentId', $assessment->getId());
            $cell = $actionsCell->append(\Tk\Table\Ui\ActionButton::createBtn($assessment->getName(), $url, $assessment->getIcon()))
                ->addOnShow(function (\Tk\Table\Cell\Iface $cell, \App\Db\Placement $placement, \Tk\Table\Ui\ActionButton $btn) use ($assessment, $spec) {
                    $placementAssessment = \Ca\Db\AssessmentMap::create()->findFiltered(
                        array('subjectId' => $placement->getSubjectId(), 'uid' => $assessment->getUid())
                    )->current();
                    if (!$placementAssessment) $placementAssessment = $assessment;

                    $btn->setUrl(\Uni\Uri::createSubjectUrl($spec, $placement->getSubject())
                        ->set('assessmentId', $placementAssessment->getId())->set('placementId', $placement->getId()));
                    if (!$placementAssessment->isAvailable($placement) || ($cell->getTable()->get('isMentorView', false) || !$this->getAuthUser()->isLearner())) {
                        $btn->setVisible(false);
                        return;
                    }

                    $entry = \Ca\Db\EntryMap::create()->findFiltered(array(
                            'assessmentId' => $placementAssessment->getId(),
                            'placementId' => $placement->getId())
                    )->current();

                    if ($entry) {
                        $btn->addCss('btn-default');
                        $btn->setText('Edit ' . $placementAssessment->getName());

                        // Add it here!!!!
                    $alertHtml = '<i class="fa fa-info-circle text-danger" style="position: absolute;top: -5px;right: -5px;z-index: 9;background: #FFF;border-radius: 50%;"></i>';
                    if ($placementAssessment->getAssessorGroup() == Assessment::ASSESSOR_GROUP_COMPANY && !Entry::isPlacementCreditEqualAssessmentClass($placement, Assessment::ASSESSOR_GROUP_COMPANY)) {
                        $btn->getTemplate()->appendHtml('link', $alertHtml);
                        $btn->setAttr('title', 'Assessment credit does not match placement credit.');
                    }
                        $alertHtml = '<i class="fa fa-info-circle text-warning" style="position: absolute;top: -5px;right: -5px;z-index: 9;background: #FFF;border-radius: 50%;"></i>';
                    if ($placementAssessment->getAssessorGroup() == Assessment::ASSESSOR_GROUP_STUDENT && !Entry::isPlacementCreditEqualAssessmentClass($placement, Assessment::ASSESSOR_GROUP_STUDENT)) {
                        $btn->getTemplate()->appendHtml('link', $alertHtml);
                        $btn->setAttr('title', 'Self-Assessment credit does not match placement credit.');
                    }


                    } else {
                        if (!$cell->getTable()->get('isMentorView', false) && $placementAssessment->getAuthUser()->isLearner()) {
                            $btn->addCss('btn-success');
                            $btn->setText('Create ' . $placementAssessment->getName());
                        }
                    }
                });
            if ($assessment->isSelfAssessment()) {
                $cell->setGroup('feedback');
            } else {
                $cell->setGroup('ca');
            }
            // Add Entry Columns for csv exports
            $aName = 'assessment_'.$assessment->getId();
            $aLabel = $assessment->getName();
            if ($assessment->getPlacementTypes()->count() == 1)
                $aLabel .= ' ['.$assessment->getPlacementTypeName().']';
            $cell = $event->getTable()->appendCell(new \Tk\Table\Cell\Text($aName), 'placementReportId')->setLabel($aLabel)->setOrderProperty('')
                ->addOnPropertyValue(function ($cell, $obj, $value) use ($assessment) {
                    /** @var $obj \App\Db\Placement */
                    $placementAssessment = \Ca\Db\AssessmentMap::create()->findFiltered(
                        array('subjectId' => $obj->getSubjectId(), 'uid' => $assessment->getUid())
                    )->current();
                    if (!$placementAssessment) $placementAssessment = $assessment;

                    if ($placementAssessment->isAvailable($obj)) {
                        $entry = \Ca\Db\EntryMap::create()->findFiltered(array(
                            'assessmentId' => $placementAssessment->getId(),
                            'placementId' => $obj->getId())
                        )->current();
                        if ($entry) {
                            return 'Yes';
                        }
                    }
                    return 'No';
                });
            /** @var \Tk\Table\Action\ColumnSelect $columns */
            $columns = $event->getTable()->findAction('columns');
            $columns->addUnselected($aName);
        }

    }

    /**
     * Check the user has access to this controller
     *
     * @param \Tk\Event\TableEvent $event
     * @throws \Exception
     */
    public function addEntryCell(\Tk\Event\TableEvent $event)
    {
        if (!$this->controller) return;

        if (!$event->getTable() instanceof \App\Table\Placement ||
            ($event->getTable()->get('isMentorView', false) || !$this->getAuthUser()->isLearner())
        ) return;
        $subjectId = $event->getTable()->get('subjectId');
        if (!$subjectId) return;

        $assessmentList = \Ca\Db\AssessmentMap::create()->findFiltered(array(
            'subjectId' => $subjectId,
            'assessorGroup' => \Ca\Db\Assessment::ASSESSOR_GROUP_COMPANY
        ));
        $table = $event->getTable();

        $table->appendCell(Text::create('supClass'), 'coClass')->setOrderProperty('')
            ->setAttr('title', 'Supervisor Assessment Category')
            ->addOnPropertyValue(function (\Tk\Table\Cell\Iface $cell, Placement $obj, $value) {
                $rule = Entry::getPlacementAssessmentValueRuleObject($obj, \Ca\Db\Assessment::ASSESSOR_GROUP_COMPANY);
                if ($rule)
                    $value = $rule->getLabel();
                return $value;
            });
        $table->appendCell(Text::create('stClass'), 'coClass')->setOrderProperty('')
            ->setAttr('title', 'Self-Assessment Category')
            ->addOnPropertyValue(function (\Tk\Table\Cell\Iface $cell, Placement $obj, $value) {
            $rule = Entry::getPlacementAssessmentValueRuleObject($obj, \Ca\Db\Assessment::ASSESSOR_GROUP_STUDENT);
            if ($rule)
                $value = $rule->getLabel();
            return $value;
        });

        $table->appendCell(\Tk\Table\Cell\Link::create('assessmentLinks'))
            ->setLabel('Assessment Links')
            ->addOnPropertyValue(function ($cell, $obj, $value) use ($assessmentList) {
                /** @var \App\Db\Placement $obj */
                $value = '';
                /** @var \Ca\Db\Assessment $assessment */
                foreach ($assessmentList as $assessment) {
                    if ($assessment->isAvailable($obj)) {
                        $value .= $assessment->getPublicUrl($obj->getHash())->toString();
                    }
                }
                return $value;
            })
            ->addOnCellHtml(function ($cell, $obj, $html) use ($assessmentList) {
                /** @var \Tk\Table\Cell\Link $cell */
                /** @var \App\Db\Placement $obj */
                $html = '';
                /** @var \Ca\Db\Assessment $assessment */
                foreach ($assessmentList as $assessment) {
                    if ($assessment->isAvailable($obj)) {
                        $html .= sprintf('<a href="%s" class="btn btn-xs btn-default" title="%s" target="_blank"><i class="%s"></i></a>',
                            htmlentities($assessment->getPublicUrl($obj->getHash())), $assessment->getName(), $assessment->getIcon());
                    }
                }
                return '<div class="btn-toolbar" role="toolbar">'.$html.'</div>';
            });

    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     *
     * @api
     */
    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::CONTROLLER => array('onControllerInit', 0),
            //\Tk\PageEvents::CONTROLLER_INIT => array('addActions', 0),
            //\Tk\PageEvents::CONTROLLER_INIT => array(array('onControllerInit', 0), array('addEntryCell', 0)),
            \Tk\Table\TableEvents::TABLE_INIT => array(array('addActions', 0), array('addEntryCell', 0))
        );
    }

}