<?php
namespace Ca\Listener;

use Tk\ConfigTrait;
use Tk\Event\Subscriber;

/**
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 */
class PlacementViewHandler implements Subscriber
{
    use ConfigTrait;

    /**
     * @var \App\Controller\Placement\View
     */
    private $controller = null;


    /**
     * @param \Tk\Event\Event $event
     * @throws \Exception
     */
    public function onControllerInit(\Tk\Event\Event $event)
    {
        /** @var \App\Controller\Placement\View $controller */
        $controller = $event->get('controller');
        if ($controller instanceof \App\Controller\Placement\View) {
            $this->controller = $controller;

            $template = $this->controller->getTemplate();
            $placement = $this->controller->getPlacement();

            $list = \Ca\Db\AssessmentMap::create()->findFiltered([
                //'subjectId' => $placement->getSubjectId(),
                'courseId' => $placement->getSubject()->getCourseId(),
                'publish' => true
            ]);
            foreach ($list as $assessment) {
                if (!$placement->getPlacementType() || !$placement->getPlacementType()->isEnableReport()) continue;
                if (!$assessment->isAvailable($placement)) continue;
                $btn = null;
                if ($assessment->isSelfAssessment()) {
                    /** @var \Ca\Db\Entry $entry */
                    $entry = \Ca\Db\EntryMap::create()->findFiltered(array(
                        'assessmentId' => $assessment->getId(),
                        'placementId' => $placement->getId()
                    ))->current();
                    if ($entry) {
                        if (($entry->getStatus() == \Ca\Db\Entry::STATUS_PENDING || $entry->getStatus() == \Ca\Db\Entry::STATUS_AMEND)
                            && ($this->getAuthUser()->isStaff() || $this->getAuthUser()->isLearner())) {
                            $url = \Uni\Uri::createSubjectUrl('/ca/entryEdit.html')
                                ->set('assessmentId', $assessment->getId())->set('placementId', $placement->getId());
                            $btn = \Tk\Ui\Link::createBtn($assessment->getName(), $url, $assessment->getIcon());
                            $btn->setAttr('title', 'Edit ' . $assessment->getName());
                            $btn->addCss('btn-info');
                        } else {
                            $url = \Uni\Uri::createSubjectUrl('/ca/entryView.html')->set('entryId', $entry->getId());
                            $btn = \Tk\Ui\Link::createBtn($assessment->getName(), $url, $assessment->getIcon());
                            $btn->setAttr('title', 'View ' . $assessment->getName());
                        }
                    } else {
                        if ($placement->getReport() && ($this->getAuthUser()->isStaff() || $this->getAuthUser()->isLearner())) {
                            $url = \Uni\Uri::createSubjectUrl('/ca/entryEdit.html')
                                ->set('assessmentId', $assessment->getId())->set('placementId', $placement->getId());
                            $btn = \Tk\Ui\Link::createBtn($assessment->getName(), $url, $assessment->getIcon());
                            $btn->setAttr('title', 'Create ' . $assessment->getName());
                            $btn->addCss('btn-success');
                        }
                    }

                } else {
                    $entry = \Ca\Db\EntryMap::create()->findFiltered(array(
                        'assessmentId' => $assessment->getId(),
                        'placementId' => $placement->getId(),
                        'status' => \Ca\Db\Entry::STATUS_APPROVED
                    ))->current();
                    if ($entry) {
                        $url = \Uni\Uri::createSubjectUrl('/ca/entryView.html')->set('entryId', $entry->getId());
                        $btn = \Tk\Ui\Link::createBtn($assessment->getName(), $url, $assessment->getIcon());
                        $btn->setAttr('title', 'View ' . $assessment->getName());
                    }
                }
                if ($btn) {
                    $btn->addCss('btn-sm');
                    $template->prependTemplate('placement-actions', $btn->show());
                }
            }
        }
    }

    /**
     * Check the user has access to this controller
     *
     * @param \Tk\Event\Event $event
     */
    public function onControllerShow(\Tk\Event\Event $event) {}

    /**
     * @return array The event names to listen to
     * @api
     */
    public static function getSubscribedEvents()
    {
        return array(
            \Tk\PageEvents::CONTROLLER_INIT => array('onControllerInit', 0),
            \Tk\PageEvents::CONTROLLER_SHOW => array('onControllerShow', 0)
        );
    }

}