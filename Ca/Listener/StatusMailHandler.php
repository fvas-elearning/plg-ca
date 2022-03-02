<?php
namespace Ca\Listener;

use App\Db\MailTemplate;
use Ca\Db\Assessment;
use Tk\Event\Subscriber;

/**
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 */
class StatusMailHandler implements Subscriber
{

    /**
     * @param \Bs\Event\StatusEvent $event
     * @throws \Exception
     */
    public function onSendAllStatusMessages(\Bs\Event\StatusEvent $event)
    {
        // do not send messages
        $course = \Uni\Util\Status::getCourse($event->getStatus());
        if (!$event->getStatus()->isNotify() || ($course && !$course->getCourseProfile()->isNotifications())) {
            //\Tk\Log::debug('Skill::onSendAllStatusMessages: Status Notification Disabled');
            return;
        }
        $subject = \Uni\Util\Status::getSubject($event->getStatus());

        /** @var \Tk\Mail\CurlyMessage $message */
        foreach ($event->getMessageList() as $message) {
            if (!$message->get('placement::id')) continue;
            /** @var \App\Db\Placement $placement */
            $placement = \App\Db\PlacementMap::create()->find($message->get('placement::id'));

            if ($placement) {
                /** @var MailTemplate $mailTemplate */
                $mailTemplate = $message->get('_mailTemplate');
                /** @var \Ca\Db\Assessment $assessment */
                $assessment = $message->get('_assessment');
                if ($assessment) {      // when a specific entry message or reminder is sent then go in here
                    $url = '';
                    switch($assessment->getAssessorGroup()) {
                        case \Ca\Db\Assessment::ASSESSOR_GROUP_STUDENT:     // Student URL
                            $url = \Uni\Uri::createSubjectUrl('/ca/entryEdit.html', $placement->getSubject(), '/student')
                                ->set('placementId', $placement->getId())
                                ->set('assessmentId', $assessment->getId())->toString();
                            break;
                        case \Ca\Db\Assessment::ASSESSOR_GROUP_COMPANY:     // Public URL
                            $url = \Uni\Uri::createInstitutionUrl('/assessment.html', $placement->getSubject()->getInstitution())
                                ->set('h', $placement->getHash())
                                ->set('assessmentId', $assessment->getId())->toString();
                            break;
                    }
                    if($mailTemplate->getRecipient() == MailTemplate::RECIPIENT_MENTOR) {
                        $url = \Uni\Uri::createSubjectUrl('/ca/entryView.html', $placement->getSubject(), '/staff')
                            ->set('placementId', $placement->getId())
                            ->set('assessmentId', $assessment->getId())->toString();
                    }
                    $linkHtml = sprintf('<a href="%s" title="%s">%s</a>', htmlentities($url),
                        htmlentities($assessment->getName()), htmlentities($assessment->getName()));
                    $linkText = sprintf('%s: %s', htmlentities($assessment->getName()), htmlentities($url));

                    $message->set('assessment::linkHtml', $linkHtml);
                    $message->set('assessment::linkText', $linkText);
                    $message->set('assessment::name', $assessment->getName());

                    // TODO: These should be deprecated where possible
                    $message->set('ca::linkHtml', $linkHtml);
                    $message->set('ca::linkText', $linkText);
                } else {    // This would be used ??????

                    $caLinkHtml = '';
                    $caLinkText = '';
                    $filter = array(
                        'active' => true,
                        'subjectId' => $message->get('placement::subjectId'),
                        'assessorGroup' => \Ca\Db\Assessment::ASSESSOR_GROUP_COMPANY,
                        'placementTypeId' => $placement->placementTypeId
                    );
                    $list = \Ca\Db\AssessmentMap::create()->findFiltered($filter);
                    //vd($filter, $list->count());
                    /** @var \Ca\Db\Assessment $assessment */
                    foreach ($list as $assessment) {
                        $key = $assessment->getNameKey();
                        $avail = '';
                        if (!$assessment->isAvailable($placement)) {
                            $avail = ' [Currently Unavailable]';
                        }
                        $url = '';

                        switch($assessment->getAssessorGroup()) {
                            case \Ca\Db\Assessment::ASSESSOR_GROUP_STUDENT:     // Student URL
                                $url = \Uni\Uri::createSubjectUrl('/ca/entryEdit.html', $placement->getSubject(), '/student')
                                    ->set('placementId', $placement->getId())
                                    ->set('assessmentId', $assessment->getId())->toString();
                                break;
                            case \Ca\Db\Assessment::ASSESSOR_GROUP_COMPANY:     // Public URL
                                $url = \Uni\Uri::createInstitutionUrl('/assessment.html', $placement->getSubject()->getInstitution())
                                    ->set('h', $placement->getHash())
                                    ->set('assessmentId', $assessment->getId())->toString();
                                break;
                        }
                        if($mailTemplate->getRecipient() == MailTemplate::RECIPIENT_MENTOR) {
                            $url = \Uni\Uri::createSubjectUrl('/ca/entryView.html', $placement->getSubject(), '/staff')
                                ->set('placementId', $placement->getId())
                                ->set('assessmentId', $assessment->getId())->toString();
                        }


                        $asLinkHtml = sprintf('<a href="%s" title="%s">%s</a>', htmlentities($url),
                            htmlentities($assessment->getName()) . $avail, htmlentities($assessment->getName()) . $avail);
                        $asLinkText = sprintf('%s: %s', htmlentities($assessment->getName()) . $avail, htmlentities($url));

                        $message->set($key.'::linkHtml', $asLinkHtml);
                        $message->set($key.'::linkText', $asLinkText);
                        $message->set($key.'::name', $assessment->getName());

                        $caLinkHtml .= sprintf('<a href="%s" title="%s">%s</a> | ', htmlentities($url),
                            htmlentities($assessment->getName()) . $avail, htmlentities($assessment->getName()) . $avail);
                        $caLinkText .= sprintf('%s: %s | ', htmlentities($assessment->getName()) . $avail, htmlentities($url));
                    }

                    $message->set('assessment::linkHtml', rtrim($caLinkHtml, ' | '));
                    $message->set('assessment::linkText', rtrim($caLinkText, ' | '));
                    // TODO: These should be deprecated where possible
                    $message->set('ca::linkHtml', rtrim($caLinkHtml, ' | '));
                    $message->set('ca::linkText', rtrim($caLinkText, ' | '));

                }

            }

        }
    }

    /**
     * @param \Tk\Event\Event $event
     * @throws \Exception
     */
    public function onTagList(\Tk\Event\Event $event)
    {
        $course = $event->get('course');
        $list = $event->get('list');

        $list['{entry::id}'] = 1;
        $list['{entry::title}'] = 'Entry Title';
        $list['{entry::assessor}'] = 'Assessor Name';
        $list['{entry::status}'] = 'approved';
        $list['{entry::notes}'] = 'Notes Text';
        $list['{entry::attachPdf}'] = 'Attach Entry PDF to email';

        $list['{assessment::id}'] = 1;
        $list['{assessment::name}'] = 'Assessment Name';
        $list['{assessment::description}'] = 'HTML description text';
        $list['{assessment::placementTypes}'] = 'StatusNames';
        $list['{assessment::linkHtml}'] = 'HTML links';
        $list['{assessment::linkText}'] = 'Text links';

        //Deprecated update all mail templates.
//        $list['{ca::linkHtml}'] = 'All available assessment HTML links';
//        $list['{ca::linkText}'] = 'All available assessment Text links';

        $aList = \Ca\Db\AssessmentMap::create()->findFiltered(array('courseId' => $course->getId()));
        foreach ($aList as $assessment) {
            $key = $assessment->getNameKey();
            $tag = sprintf('{%s}{/%s}', $key, $key);
            $list[$tag] = 'Assessment block';
            // Hidden do not encourage use of these at the moment only by developers as needed.
//            $list[sprintf('{%s::linkHtml}', $key)] = '<a href="http://www.example.com/form.html" title="Assessment">Assessment</a>';
//            $list[sprintf('{%s::linkText}', $key)] = 'Assessment: http://www.example.com/form.html';
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
            \Bs\StatusEvents::STATUS_SEND_MESSAGES => array('onSendAllStatusMessages', 10),
            \App\AppEvents::MAIL_TEMPLATE_TAG_LIST => array('onTagList', 10)
        );
    }
    
}