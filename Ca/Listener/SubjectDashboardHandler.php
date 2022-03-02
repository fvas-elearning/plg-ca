<?php
namespace Ca\Listener;

use Tk\Event\Event;
use Tk\Event\Subscriber;

/**
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 */
class SubjectDashboardHandler implements Subscriber
{

    /**
     * @var \App\Db\Subject|\Uni\Db\SubjectIface
     */
    private $subject = null;

    /**
     * @var \App\Controller\Iface
     */
    protected $controller = null;



    /**
     * constructor.
     * @param \App\Db\Subject|\Uni\Db\SubjectIface $subject
     */
    public function __construct($subject)
    {
        $this->subject = $subject;
    }

    /**
     * Check the user has access to this controller
     *
     * @param Event $event
     * @throws \Exception
     */
    public function onControllerInit(Event $event)
    {
        /** @var \App\Controller\Staff\SubjectDashboard $controller */
        $this->controller = $event->get('controller');
        $subject = $this->controller->getConfig()->getSubject();

        // STUDENT Subject Dashboard
        if ($this->controller instanceof \App\Controller\Student\SubjectDashboard) {
            $placementList = $this->controller->getPlacementList();
            $actionCell = $placementList->getActionCell();

            $list = \Ca\Db\AssessmentMap::create()->findFiltered(array(
                'subjectId' => $subject->getId()
            ));
            foreach ($list as $assessment) {
                $actionCell->addButton(\Tk\Table\Cell\ActionButton::create($assessment->getName(),
                    \Uni\Uri::createSubjectUrl('/ca/entryView.html'), $assessment->getIcon()))
                    ->setShowLabel()
                    ->addOnShow(function ($cell, $obj, $btn) use ($assessment) {
                        /** @var \Tk\Table\Cell\Actions $cell */
                        /** @var \App\Db\Placement $obj */
                        /** @var \Tk\Table\Cell\ActionButton $btn */
                        if (!$obj->getPlacementType() || !$obj->getPlacementType()->isEnableReport() || !$assessment->isAvailable($obj)) {
                            $btn->setVisible(false);
                            return;
                        }

                        $entry = $assessment->findEntry($obj);
                        if ($entry) {
                            $btn->setAttr('title', 'View ' . $assessment->getName());
                            if ($assessment->getAssessorGroup() == \Ca\Db\Assessment::ASSESSOR_GROUP_STUDENT) {
                                if ($entry->hasStatus(array(\Ca\Db\Entry::STATUS_PENDING, \Ca\Db\Entry::STATUS_AMEND))) {
                                    $btn->addCss('btn-info');
                                    $btn->setAttr('title', 'Edit ' . $assessment->getName());
                                    $btn->setUrl(\Uni\Uri::createSubjectUrl('/ca/entryEdit.html')->set('placementId', $obj->getId())->set('assessmentId', $assessment->getId()));
                                }
                            } else {
                                if (!$entry->hasStatus(array(\Ca\Db\Entry::STATUS_APPROVED))) {
                                    $btn->setVisible(false);
                                }
                            }
                            $btn->getUrl()->set('entryId', $entry->getId());
                        } else {
                            if ($assessment->getAssessorGroup() == \Ca\Db\Assessment::ASSESSOR_GROUP_STUDENT && $obj->getReport()) {
                                $btn->setAttr('title', 'Create ' . $assessment->getName());
                                $btn->addCss('btn-success');
                                $btn->setUrl(\Uni\Uri::createSubjectUrl('/ca/entryEdit.html')->set('placementId', $obj->getId())->set('assessmentId', $assessment->getId()));
                            } else {
                                $btn->setVisible(false);
                            }
                        }
                    });
            }

        }
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
            \Tk\PageEvents::CONTROLLER_INIT => array('onControllerInit', 0)
        );
    }
    
}