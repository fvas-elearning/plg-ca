<?php
namespace Ca\Listener;

use Symfony\Component\Console\Output\Output;
use Tk\ConfigTrait;
use Tk\Event\Event;
use Tk\Event\Subscriber;

/**
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 *
 * @todo See todo.md as this need to be refactored to be a more maintainable codebase
 *
 */
class CronHandler implements Subscriber
{
    use ConfigTrait;


    /**
     * @param Event $event
     * @throws \Exception
     * @deprecated
     */
    public function onCron(Event $event)
    {
        /** @var \App\Console\Cron $console */
        $console = $event->get('console');

        $this->sendReminders($console);
        $console->write(' - Assessment Reminders Complete!');
    }


    /**
     * @TODO: Need to search for all placements with outstanding evaluations????
     * @TODO:  1. Create some assessment settings that have a firstReminder and recurringReminder and recurringMax
     * @TODO:  2. This should be placed into the competency Assessment plugin tho? Unless we create a EMS reminder system?
     *
     * @note: This will only find placements without an evaluation record.
     * @note: It cannot find entries that are in a status as that does not
     * @note:   make logical sense and confuses the business logic trying to be achieved.
     *
     * @param \App\Console\Cron $console
     * @throws \Exception
     */
    protected function sendReminders($console)
    {
        $console->write(' - Sending Assessment Reminders');

        // Placement Evaluating reminder after 7 days, then send every 28 days, for max 4 months.

//        $console->writeBlue('    - TODO: Send initial assessment reminder');
//        $console->writeBlue('    - TODO: Send any outstanding reminders less than max number of times.');
//        $console->writeBlue('    - TODO: For any reminders on their last send, also email the subject coordinator informing them of the issue.');
//        $console->write(' ');


        // Get enabled courses
        $plugin = \Ca\Plugin::getInstance();
        $courseList = $plugin->getPluginFactory()->getPluginZoneIdList($plugin->getName(), \App\Plugin\Iface::ZONE_COURSE);
        $now = $console->getNow();

        //$courseList = array_reverse($courseList);
        foreach ($courseList as $courseData) {
            /** @var \Uni\Db\CourseIface $course */
            $course = $this->getConfig()->getCourseMapper()->find($courseData->zone_id);
            if (!$course) continue;
            $assessmentList = \Ca\Db\AssessmentMap::create()->findFiltered(array('courseId' => $courseData->zone_id, 'enableReminder' => true));
            if (!$assessmentList->countAll()) continue;

            // TODO: be sure this only affects the reminder emails
            $mailTemplateList = \App\Db\MailTemplateMap::create()->findFiltered(array(
                'active' => true,
                'courseId' => $course->getId(),
                'event' => 'message.ca.entry.reminder'
            ));
            if (!$mailTemplateList->countAll()) {
                $console->writeRed('   `Assessment Entry - Reminder` Template not found for course: ' . $course->getName());
                continue;
            }


            $courseFound = false;
            $subjectList = $this->getConfig()->getSubjectMapper()->findFiltered(array('courseId' => $course->getId(), 'active' => true), \Tk\Db\Tool::create('id DESC'));
            foreach ($subjectList as $subject) {
                if (!\Ca\Db\AssessmentMap::create()->hasSubject($subject->getId())) continue;
                if (!$courseFound) {
                    $console->write('    Course: ' . $course->getName());
                    $courseFound = true;
                }

                $subjectFound = false;
                foreach ($assessmentList as $i => $assessment) {
                    if (!$assessment->isEnableReminder() || !$assessment->isActive($subject->getId())) continue;
                    // Get a list of placements with no assessments, only for assessing or evaluating status of placements
                    $res = \Ca\Db\EntryMap::create()->findReminders($assessment, $subject, $now);
                    if (!$res->rowCount()) continue;

                    if (!$subjectFound && $res->rowCount()) {
                        $console->write('      Subject: ' . $subject->getName());
                        $subjectFound = true;
                    }

                    //$date =  new \DateTime('today -'.$assessment->getReminderInitialDays().' days');
                    $date = clone $now;
                    $date = $date->add(new \DateInterval('P'.$assessment->getReminderInitialDays().'D'));

                    $console->writeComment('       Assessment: ' . $assessment->getName() . ' - ' . $assessment->getPlacementTypeName() . ' [' . $assessment->getId() . ']');
                    $console->writeComment('        Date From: ' . $date->format(\Tk\Date::FORMAT_SHORT_DATE));
                    $console->writeComment('        Empty Entries: ' . $res->rowCount());
                    $sentCnt = 0;;
                    foreach($res as $row) {
                        /** @var \App\Db\Placement $placement */
                        $placement = \App\Db\PlacementMap::create()->find($row->id);
                        if (!$placement) continue;

                        // Calculate the next due reminder date for this placememnt/Assessment
                        $nextReminderDate = \Tk\Date::floor($placement->getDateEnd()
                            ->add(new \DateInterval('P'.$assessment->getReminderInitialDays().'D')));
                        if ($row->last_sent) {
                            $lastSent = \Tk\Date::floor(\Tk\Date::create($row->last_sent));
                            $nextReminderDate = \Tk\Date::floor($lastSent
                                ->add(new \DateInterval('P' . $assessment->getReminderRepeatDays() . 'D')));
                        }

                        // compare dates, last date and number of reminders sent to see what should be sent and what should not.
                        if (!$row->last_sent || $now >= $nextReminderDate ) {
                            $entry = \Ca\Db\Entry::create($placement, $assessment);      // Do not save() this status and entry..
                            $entry->setStatus('reminder');

                            $status = $this->getConfig()->createStatus($entry);
                            $status->setName('Assessment Reminder');
                            $status->setEvent('message.ca.entry.reminder');

                            $e = new \Bs\Event\StatusEvent($status);
                            $this->getConfig()->getEventDispatcher()->dispatch(\Bs\StatusEvents::STATUS_CHANGE, $e);
                            $this->getConfig()->getEventDispatcher()->dispatch(\Bs\StatusEvents::STATUS_SEND_MESSAGES, $e);
                        //\Tk\Log::warning($placement->getTitle() . ': ' . $placement->getStatus());
                            if ($e->isPropagationStopped()) continue;

                            // Mark reminder sent
                            $sentCnt += $e->get('sent', 0);
                            if ($e->get('sent', 0)) {
                                \Ca\Db\EntryMap::create()->addReminderLog($assessment->getId(), $placement->getId(), $now);
                            }
                        }
                    }
                    $console->writeComment('        Reminders: ' . $sentCnt);
                }
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
            \App\AppEvents::CONSOLE_CRON => array('onCron', 0)
        );
    }
    
}