<?php
namespace Ca\Listener;

use Ca\Plugin;
use Symfony\Component\HttpKernel\KernelEvents;
use Tk\ConfigTrait;
use Tk\Event\Subscriber;

/**
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 */
class SetupHandler implements Subscriber
{
    use ConfigTrait;

    /**
     * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
     * @throws \Exception
     */
    public function onRequest($event)
    {
        $this->setup();
    }

    /**
     * @param \Symfony\Component\Console\Event\ConsoleCommandEvent $event
     * @throws \Exception
     */
    public function onCommand(\Symfony\Component\Console\Event\ConsoleCommandEvent $event)
    {
        $config = \Uni\Config::getInstance();
        $dispatcher = $config->getEventDispatcher();

        $dispatcher->addSubscriber(new \Ca\Listener\CronHandler());
        $dispatcher->addSubscriber(new \Ca\Listener\StatusMailHandler());

        $this->setup();
    }

    /**
     * @throws \Exception
     */
    public function setup()
    {
        /* NOTE:
         *  If you require the Institution object for an event
         *  be sure to subscribe events here.
         *  As any events fired before this event do not have access to
         *  the institution object, unless you manually save the id in the
         *  session on first page load?
         */
        $config = \Uni\Config::getInstance();
        $dispatcher = $config->getEventDispatcher();
        $plugin = Plugin::getInstance();

///        $institution = \Uni\Config::getInstance()->getInstitution()
//        if($institution && $plugin->isZonePluginEnabled(Plugin::ZONE_INSTITUTION, $institution->getId())) {
//            \Tk\Log::debug($plugin->getName() . ': Sample init client plugin stuff: ' . $institution->name);
//            $dispatcher->addSubscriber(new \Ca\Listener\ExampleHandler(Plugin::ZONE_INSTITUTION, $institution->getId()));
//        }

        $subject = \Uni\Config::getInstance()->getSubject();
        if ($subject && $plugin->isZonePluginEnabled(Plugin::ZONE_SUBJECT, $subject->getId())) {
//            \Tk\Log::debug($plugin->getName() . ': Sample init subject plugin stuff: ' . $subject->name);
//            $dispatcher->addSubscriber(new \Ca\Listener\ExampleHandler(Plugin::ZONE_SUBJECT, $subject->getId()));
        }
        $dispatcher->addSubscriber(new \Ca\Listener\PlacementManagerHandler());
        $dispatcher->addSubscriber(new \Ca\Listener\StudentAssessmentHandler());
        $dispatcher->addSubscriber(new \Ca\Listener\AssessmentUnitsHandler());

        $course = \App\Config::getInstance()->getCourse();
        if ($course && $plugin->isZonePluginEnabled(Plugin::ZONE_COURSE, $course->getId())) {
            $subject = \Uni\Config::getInstance()->getSubject();
            if ($subject) {
                $dispatcher->addSubscriber(new \Ca\Listener\PlacementReportHandler($subject));
                $dispatcher->addSubscriber(new \Ca\Listener\PlacementEditHandler($subject));
                $dispatcher->addSubscriber(new \Ca\Listener\PlacementViewHandler());
                $dispatcher->addSubscriber(new \Ca\Listener\SubjectDashboardHandler($subject));
            }
            $dispatcher->addSubscriber(new \Ca\Listener\CourseEditHandler());
        }
        $dispatcher->addSubscriber(new \Ca\Listener\SubjectEditHandler());
        $dispatcher->addSubscriber(new \Ca\Listener\StatusMailHandler());
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
            KernelEvents::REQUEST => array('onRequest', -10),
            \Symfony\Component\Console\ConsoleEvents::COMMAND  => array('onCommand', -10)
        );
    }
    
    
}