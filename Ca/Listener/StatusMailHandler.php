<?php
namespace Ca\Listener;

use Tk\Event\Subscriber;

/**
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 */
class StatusMailHandler implements Subscriber
{

    /**
     * @param \App\Event\StatusEvent $event
     * @throws \Exception
     */
    public function onSendAllStatusMessages(\App\Event\StatusEvent $event)
    {
        if (!$event->getStatus()->notify || !$event->getStatus()->getProfile()->notifications) return;   // do not send messages

        /** @var \Tk\Mail\CurlyMessage $message */
        foreach ($event->getMessageList() as $message) {

            if ($message->get('placement::id')) {
                /** @var \App\Db\Placement $placement */
                $placement = \App\Db\PlacementMap::create()->find($message->get('placement::id'));
                if ($placement) {
                    $filter = array(
                        'active' => true,
                        'subjectId' => $message->get('placement::subjectId'),
                        'role' => \Ca\Db\Assessment::ASSESSOR_GROUP_COMPANY,
                        'assessorGroup' => \Ca\Db\Assessment::ASSESSOR_GROUP_COMPANY,
                        //'requirePlacement' => true,
                        'placementTypeId' => $placement->placementTypeId
                    );
                    $list = \Ca\Db\AssessmentMap::create()->findFiltered($filter);
                    /** @var \Ca\Db\Assessment $assessment */
                    foreach ($list as $assessment) {
                        $key = $assessment->getNameKey();
                        $url = \Uni\Uri::createInstitutionUrl('/assessment.html', $placement->getSubject()->getInstitution())
                            ->set('h', $placement->getHash())
                            ->set('assessmentId', $assessment->getId());
                        $avail = '';
                        if (!$assessment->isAvailable($placement)) {
                            $avail = ' [Currently Unavailable]';
                        }

                        $caLinkHtml = sprintf('<a href="%s" title="%s">%s</a>', htmlentities($url->toString()),
                                htmlentities($assessment->getName()) . $avail, htmlentities($assessment->getName()) . $avail);
                        $caLinkText = sprintf('%s: %s', htmlentities($assessment->getName()) . $avail, htmlentities($url->toString()));

                        $message->set($key.'::linkHtml', $caLinkHtml);
                        $message->set($key.'::linkText', $caLinkText);
//                        $message->set($key.'::id', $assessment->getId());
//                        $message->set($key.'::name', $assessment->getName());
//                        $message->set($key.'::placementStatus', $assessment->getPlacementStatus());
//                        $message->set($key.'::description', $assessment->getDescription());

                    }
                }
            }
        }
    }

    public function onTagList(\Tk\Event\Event $event)
    {
        $profile = $event->get('profile');
        $list = $event->get('list');

        $list['{assessment::id}'] = 1;
        $list['{assessment::name}'] = 'Assessment Name';
        $list['{assessment::description}'] = 'HTML discription text';

        $list['{entry::id}'] = 1;
        $list['{entry::title}'] = 'Entry Title';
        $list['{entry::assessor}'] = 'Assessor Name';
        $list['{entry::status}'] = 'approved';
        $list['{entry::notes}'] = 'Notes Text';

        $aList = \Ca\Db\AssessmentMap::create()->findFiltered(array('courseId' => $profile->getId()));
        foreach ($aList as $assessment) {
            $key = $assessment->getNameKey();
            $tag = sprintf('{%s}{/%s}', $key, $key);
            $list[$tag] = 'Assessment block';
            $list[sprintf('{%s::linkHtml}', $key)] = '<a href="http://www.example.com/form.html" title="Assessment">Assessment</a>';
            $list[sprintf('{%s::linkText}', $key)] = 'Assessment: http://www.example.com/form.html';
//            $list[sprintf('{%s::id}', $key)] = 1;
//            $list[sprintf('{%s::name}', $key)] = 'Assessment Name';
//            $list[sprintf('{%s::placementStatus}', $key)] = 'approved, failed, ...';
//            $list[sprintf('{%s::description}', $key)] = 'HTML discription text';
        }

        $event->set('list', $list);
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
            \App\StatusEvents::STATUS_SEND_MESSAGES => array('onSendAllStatusMessages', 10),
            \App\AppEvents::MAIL_TEMPLATE_TAG_LIST => array('onTagList', 10)
        );
    }
    
}