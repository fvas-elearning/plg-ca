<?php
namespace Ca\Listener;

use Tk\ConfigTrait;
use Tk\Event\Event;
use Tk\Event\Subscriber;

/**
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 */
class PlacementEditHandler implements Subscriber
{
    use ConfigTrait;

    /**
     * @var \App\Db\Subject
     */
    private $subject = null;

    /**
     * @var \App\Controller\Placement\Edit
     */
    private $controller = null;

    /**
     * @var null|\Ca\Db\Assessment[]
     */
    private $list = null;



    /**
     * PlacementEditHandler constructor.
     * @param $subject
     */
    public function __construct($subject)
    {
        $this->subject = $subject;
    }

    /**
     * @param Event $event
     * @throws \Exception
     */
    public function onPageInit(Event $event)
    {
        /** @var \App\Controller\Placement\Edit $controller */
        $controller = $event->get('controller');
        if ($controller instanceof \App\Controller\Placement\Edit) {
            $this->controller = $controller;
        }
    }

    /**
     * @param \Tk\Event\FormEvent $event
     * @throws \Exception
     */
    public function onFormLoad(\Tk\Event\FormEvent $event)
    {
        if ($this->controller) {
            if (!$this->controller->getPlacement()) return;
            $placement = $this->controller->getPlacement();
            $form = $event->getForm();

            $this->list = \Ca\Db\AssessmentMap::create()->findFiltered(array(
                'subjectId' => $this->subject->getId(),
                //'placementStatus' => $placement->status,
                'assessorGroup' => \Ca\Db\Assessment::ASSESSOR_GROUP_COMPANY,
                'placementTypeId' => $placement->placementTypeId
            ));


            if ($placement->getId()) {
                /** @var \Ca\Db\Assessment $assessment */
                foreach ($this->list as $assessment) {
                    $url = \Uni\Uri::createInstitutionUrl('/assessment.html', $placement->getSubject()->getInstitution())
                        ->set('h', $placement->getHash())
                        ->set('assessmentId', $assessment->getId());
                    $form->appendField(new \App\Form\Field\InputLink('assessment-' . $assessment->getId()))
                        ->setTabGroup('Details')->setReadonly()->setLabel($assessment->getName())
                        ->setFieldset('Public Assessment Url`s')->setValue($url->toString())
                        ->setNotes('Copy this URL to send to the ' .
                            \App\Db\Phrase::findValue('company', $placement->getSubject()->getCourseId()) .
                            ' to access this ' . \App\Db\Phrase::findValue('placement', $placement->getSubject()->getCourseId(), true) .
                            ' ' . $assessment->getName() . ' assessment form.')
                    ->setCopyEnabled();
                }
            }
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
            if (!$this->controller->getPlacement() || !$this->controller->getPlacement()->getId()) return;
            $placement = $this->controller->getPlacement();

            /** @var \Tk\Ui\Admin\ActionPanel $actionPanel */
            $actionPanel = $this->controller->getActionPanel();

            $this->list = \Ca\Db\AssessmentMap::create()->findFiltered(array(
                'subjectId' => $this->subject->getId(),
                'placementTypeId' => $placement->placementTypeId
            ));

            /** @var \Ca\Db\Assessment $assessment */
            foreach ($this->list as $assessment) {
                if (!$assessment->isAvailable($placement)) continue;
                $entry = \Ca\Db\EntryMap::create()->findFiltered(array(
                    'assessmentId' => $assessment->getId(),
                    'placementId' => $placement->getId())
                )->current();

                $url = \Uni\Uri::createSubjectUrl('/ca/entryEdit.html');
                if ($entry) {
                    $url->set('entryId', $entry->getId());
                } else {
                    $url->set('assessmentId', $assessment->getId())
                        ->set('placementId', $placement->getId());
                }

                $btn = $actionPanel->append(\Tk\Ui\Link::createBtn($assessment->getName(), $url, $assessment->getIcon()));
                if ($entry) {
                    $btn->addCss('btn-default');
                    $btn->setAttr('title', 'Edit ' . $assessment->getName());
                } else {
                    $btn->addCss('btn-success');
                    $btn->setAttr('title', 'Create ' . $assessment->getName());
                }
            }
        }
    }

    /**
     * Check the user has access to this controller
     *
     * @param Event $event
     * @throws \Exception
     */
    public function onControllerShow(Event $event)
    {
//        $plugin = Plugin::getInstance();
//        $config = $plugin->getConfig();


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
            \Tk\PageEvents::PAGE_INIT => array('onPageInit', 0),
            \Tk\Form\FormEvents::FORM_LOAD => array('onFormLoad', 0),
            \Tk\PageEvents::CONTROLLER_INIT => array('onControllerInit', 0),
            \Tk\PageEvents::CONTROLLER_SHOW => array('onControllerShow', 0)
        );
    }
    
}