<?php
namespace Ca\Db;

use App\Db\Notice;
use Bs\Db\Status;

/**
 * @author Michael Mifsud <info@tropotek.com>
 * @see http://www.tropotek.com/
 * @license Copyright 2015 Michael Mifsud
 */
class EntryNoticeStrategy extends \App\Db\NoticeStrategyInterface
{

    /**
     * @param Notice $notice
     * @throws \Exception
     */
    public function init($notice)
    {
        /** @var \Ca\Db\Entry $obj */
        $obj = $notice->getModel();
        $notice->setParam(Notice::PARAM_ICON, 'fa fa-gavel');
        $notice->setParam(\App\Db\Notice::PARAM_STAFF_URL,
            \Uni\Uri::createSubjectUrl('/ca/entryEdit.html', $notice->getSubject(), '/staff')
                ->set('placementId', $obj->getPlacementId())->toRelativeString(false));
    }

    /**
     * @param Status $status
     * @throws \Exception
     */
    public function executeStatus($status)
    {
        /** @var \Ca\Db\Entry $entry */
        $entry = $status->getModel();
        $student = $entry->getStudent();
        $assessmentName = $entry->getAssessment()->getName();
        $notice = \App\Db\Notice::create($entry);
        $notice->setType($status->getName());

        if($status->name === \Ca\Db\Entry::STATUS_AMEND && $entry->getAssessment()->isSelfAssessment()) {
            $notice->setParam(\App\Db\Notice::PARAM_STUDENT_URL,
                \Uni\Uri::createSubjectUrl('/ca/entryEdit.html', $entry->getSubject(), '/student')
                    ->set('placementId', $entry->getPlacement()->getId())->set('assessmentId', $entry->getAssessmentId())
                    ->toRelativeString(false));

            $notice->msgSubject = sprintf('Your %s record requires updating.', $assessmentName);
            $notice->body .= sprintf('%s: `%s`', htmlentities($assessmentName), htmlentities($entry->getTitle()));
            if ($status->getMessage())
                $notice->body .= "<br/>\n" . $status->getMessage();
        }

        if ($notice->msgSubject) {   // Send notification message
            $notice->save();
            $notice->addRecipient($student);
        }

    }


}