<?php
namespace Ca\Listener;

use Tk\Event\Subscriber;
use Tk\Form\Field\Checkbox;


/**
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 */
class CourseEditHandler implements Subscriber
{

    /**
     * @param \Tk\Event\Event $event
     * @throws \Exception
     */
    public function onControllerInit(\Tk\Event\Event $event)
    {
        /** @var \App\Controller\Course\Edit $controller */
        $controller = $event->get('controller');
        if ($controller instanceof \App\Controller\Course\Edit) {
            if ($controller->getAuthUser()->isStaff() && $controller->getCourse()) {
                /** @var \Tk\Ui\Admin\ActionPanel $actionPanel */
                $actionPanel = $controller->getActionPanel();
                $actionPanel->append(\Tk\Ui\Link::createBtn('Assessment Forms',
                    \Uni\Uri::createHomeUrl('/ca/assessmentManager.html')
                        ->set('courseId', $controller->getCourse()->getId()), 'fa fa-gavel'));
            }
        }
    }

    /**
     * @param \Tk\Event\Event $event
     */
    public function onControllerShow(\Tk\Event\Event $event)
    {
        /** @var \App\Controller\Course\Edit $controller */
        $controller = $event->get('controller');
        if ($controller instanceof \App\Controller\Course\Edit) {

        }

    }


    /**
     * @param \Tk\Event\FormEvent $event
     * @throws \Exception
     */
    public function onFormInit(\Tk\Event\FormEvent $event)
    {
        if ($event->getForm() instanceof \Uni\Form\Course) {
            $tab = 'Placement';
            $f = $event->getForm()->appendField(Checkbox::create('placementCheck')->setCheckboxLabel('Show warnings when placement class does not match Supervisor Assessment.'))->setTabGroup($tab);
            $courseData = $event->getForm()->getCourse()->getData();
            $f->setValue($courseData->get('placementCheck', ''));
        }
    }


    /**
     * @param \Tk\Event\FormEvent $event
     * @throws \Exception
     */
    public function onFormSubmit(\Tk\Event\FormEvent $event)
    {
        if ($event->getForm() instanceof \Uni\Form\Course) {
            if ($event->getForm()->hasErrors()) return;
            $courseData = $event->getForm()->getCourse()->getData();
            $courseData->set('placementCheck', $event->getForm()->getFieldValue('placementCheck'));
            $courseData->save();
        }
    }




    /**
     * @return array The event names to listen to
     * @api
     */
    public static function getSubscribedEvents()
    {
        return array(
            \Tk\PageEvents::CONTROLLER_INIT => array('onControllerInit', 0),
            \Tk\PageEvents::CONTROLLER_SHOW => array('onControllerShow', 0),
            \Tk\Form\FormEvents::FORM_INIT =>  array('onFormInit', 0),
            //\Tk\Form\FormEvents::FORM_LOAD =>  array('onFormLoad', 0),
            \Tk\Form\FormEvents::FORM_SUBMIT =>  array('onFormSubmit', 0),
        );
    }
    
}