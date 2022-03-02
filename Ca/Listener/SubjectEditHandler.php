<?php
namespace Ca\Listener;

use App\Event\SubjectEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Tk\ConfigTrait;
use Tk\Event\Event;
use Tk\Event\Subscriber;

/**
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 */
class SubjectEditHandler implements Subscriber
{
    use ConfigTrait;

    /**
     * @var \App\Controller\Subject\Edit
     */
    protected $controller = null;


    /**
     * @param \Symfony\Component\HttpKernel\Event\FilterControllerEvent $event
     */
    public function onKernelController($event)
    {
        /** @var \App\Controller\Subject\Edit $controller */
        $controller = \Tk\Event\Event::findControllerObject($event);
        if ($controller instanceof \App\Controller\Subject\Edit) {
            $this->controller = $controller;
        }
    }

    /**
     * Check the user has access to this controller
     *
     * @param Event $event
     * @throws \Exception
     */
    public function onControllerInit(Event $event)
    {
        if ($this->controller) {
            if (!$this->controller->getAuthUser()->isStaff() || !$this->controller->getSubject()->getId()) return;
            /** @var \Tk\Ui\Admin\ActionPanel $actionPanel */
            $actionPanel = $this->controller->getActionPanel();
            $actionPanel->append(\Tk\Ui\Link::createBtn('Active Assessments',
                \Uni\Uri::createSubjectUrl('/ca/activeAssessments.html'), 'fa fa-gavel'));
        }
    }

    /**
     * @param SubjectEvent $event
     * @throws \Exception
     */
    public function onSubjectPostClone(SubjectEvent $event)
    {
        // Copy current subject Active CA Assessments
        $list = \Ca\Db\AssessmentMap::create()->findFiltered(['subjectId' => $event->getSubject()->getId()]);
        foreach ($list as $assessment) {
            if ($assessment->isActive($event->getSubject()->getId()))
                \Ca\Db\AssessmentMap::create()->addSubject($event->getClone()->getId(), $assessment->getId());
        }
    }


    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::CONTROLLER => array('onKernelController', 0),
            \Tk\PageEvents::CONTROLLER_INIT => array('onControllerInit', 0),
            \App\AppEvents::SUBJECT_POST_CLONE => 'onSubjectPostClone'
        );
    }
    
}