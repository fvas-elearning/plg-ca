<?php
namespace Ca\Db;

use App\Db\MailTemplate;
use App\Db\Placement;
use App\Db\Traits\PlacementTrait;
use App\Db\User;
use App\Util\StatusMessage;
use Bs\Db\Status;
use Bs\Db\Traits\TimestampTrait;
use Ca\Db\Traits\AssessmentTrait;
use Dom\Template;
use Tk\Mail\CurlyMessage;
use Uni\Config;
use Bs\Db\Traits\StatusTrait;
use Uni\Db\Traits\SubjectTrait;

/**
 * @author Mick Mifsud
 * @created 2019-11-06
 * @link http://tropotek.com.au/
 * @license Copyright 2019 Tropotek
 */
class Entry extends \Tk\Db\Map\Model implements \Tk\ValidInterface
{
    use AssessmentTrait;
    use SubjectTrait;
    use PlacementTrait;
    use TimestampTrait;
    use StatusTrait;

    const STATUS_PENDING        = 'pending';
    const STATUS_APPROVED       = 'approved';
    const STATUS_AMEND          = 'amend';
    const STATUS_NOT_APPROVED   = 'not approved';

    /**
     * @var int
     */
    public $id = 0;

    /**
     * @var int
     */
    public $assessmentId = 0;

    /**
     * @var int
     */
    public $subjectId = 0;

    /**
     * @var int
     */
    public $studentId = 0;

    /**
     * @var int
     */
    public $assessorId = 0;

    /**
     * @var int
     */
    public $placementId = 0;

    /**
     * @var string
     */
    public $title = '';

    /**
     * @var string
     */
    public $assessorName = '';

    /**
     * @var string
     */
    public $assessorEmail = '';

    /**
     * @var int
     */
    public $absent = 0;

    /**
     * @var float
     */
    public $average = 0;

    /**
     * @var string
     */
    public $status = self::STATUS_PENDING;

    /**
     * @var string
     */
    public $notes = '';

    /**
     * @var \DateTime
     */
    public $modified = null;

    /**
     * @var \DateTime
     */
    public $created = null;

    /**
     * @var null|\Uni\Db\UserIface
     */
    private $_student = null;

    /**
     * @var null|\Uni\Db\UserIface
     */
    private $_assessor = null;


    /**
     * Entry
     */
    public function __construct()
    {
        $this->_TimestampTrait();

    }

    /**
     * @param \App\Db\Placement $placement
     * @param \Ca\Db\Assessment $assessment
     * @return Entry
     */
    public static function create($placement, $assessment)
    {
        $entry = new \Ca\Db\Entry();
        $entry->setAssessmentId($assessment->getId());
        $entry->setSubjectId($placement->getSubjectId());
        $entry->setStudentId($placement->getUserId());
        $entry->setAssessorId($placement->getSupervisorId());
        $entry->setPlacementId($placement->getId());
        switch($assessment->getAssessorGroup()) {
            case \Ca\Db\Assessment::ASSESSOR_GROUP_COMPANY:
                if ($entry->getAssessor()) {
                    $entry->setAssessorName($entry->getAssessor()->getName());
                    $entry->setAssessorEmail($entry->getAssessor()->getEmail());
                }
                break;
            case \Ca\Db\Assessment::ASSESSOR_GROUP_STUDENT:
                if ($entry->getStudent()) {
                    $entry->setAssessorName($entry->getStudent()->getName());
                    $entry->setAssessorEmail($entry->getStudent()->getEmail());
                }
                break;
        }

        return $entry;
    }

    /**
     * (requires Rule plugin)
     * This method will check the placement credit from the rules plugin and see if the rule->name = option->name
     *    for completed/pending assessments
     *
     *
     * @param Placement $placement
     * @return bool
     * @throws \Exception
     */
    public static function isPlacementCreditEqualAssessmentClass($placement, $assessorGroup = 'company', $scaleId = 7)
    {
        // Check to see if the Placement rule credit matches the supervisor assessment (may move it to a method)
        if (!class_exists('\\Rs\\Calculator')) return true;
        /** @var \Rs\Db\Rule $rule */
        $rule = \Rs\Calculator::findPlacementRuleList($placement, false)->current();
        $assessmentValue = self::getAssessmentScaleValue($placement, $assessorGroup, $scaleId);
        if (!$assessmentValue) return true;
        $options = OptionMap::create()->findFiltered(['scaleId' => $scaleId]);
        if (!count($options)) return true;
        $selectedOption = null;
        foreach($options as $opt) {
            if ($opt->getValue() == $assessmentValue) {
                $selectedOption = $opt;
                break;
            }
        }
        if ($selectedOption && (strtolower($rule->getName()) != strtolower($selectedOption->getName()))) {
            return false;
        }
        return true;
    }

    /**
     * Return the Rule object for a placement by name comparison if found in an assessment and scale/item
     *
     * @param Placement $placement
     * @param string $assessorGroup
     * @param int $scaleId
     * @return \Rs\Db\Rule|null
     */
    public static function getPlacementAssessmentValueRuleObject($placement, $assessorGroup = 'company', $scaleId = 7)
    {
        $selectedRule = null;
        if (!class_exists('\\Rs\\Calculator')) return $selectedRule;
        $assessmentValue = self::getAssessmentScaleValue($placement, $assessorGroup, $scaleId);
        if (!$assessmentValue) return $selectedRule;
        $options = OptionMap::create()->findFiltered(['scaleId' => $scaleId]);
        if (!count($options)) return $selectedRule;
        $selectedOption = null;
        foreach($options as $opt) {
            if ($opt->getValue() == $assessmentValue) {
                $selectedOption = $opt;
                break;
            }
        }
        $rules = \Rs\Calculator::findSubjectRuleList($placement->getSubject(), false);
        foreach ($rules as $r) {
            if (strtolower($r->getName()) == strtolower($selectedOption->getName()))
                return $r;
        }

        return null;
    }

    /**
     * This method retrieves an assessment value from an assessment.
     *
     * If multiple assessments of the group are found the first one found is used.
     * If multiple items of the assessment are found the first one found is used.
     * If multiple entries are found the first one found is used
     *
     * @param $placement
     * @param string $assessorGroup
     * @param int $scaleId
     * @param string[] $entryStatus
     * @return string|int
     * @throws \Exception
     */
    public static function getAssessmentScaleValue($placement, $assessorGroup = 'company', $scaleId = 7, $entryStatus = [\Ca\Db\Entry::STATUS_APPROVED, \Ca\Db\Entry::STATUS_PENDING])
    {
        $assessments = \Ca\Db\AssessmentMap::create()->findFiltered(array(
            'subjectId' => $placement->getSubjectId(),
            'placementTypeId' => $placement->getPlacementTypeId(),
            'enableCheckbox' => true,
            'assessorGroup' => $assessorGroup
        ))->toArray('id');
        if (!count($assessments)) return '';

        /** @var \Ca\Db\Entry $entry */
        $entry = \Ca\Db\EntryMap::create()->findFiltered(array(
            'assessmentId' => $assessments,
            'placementId' => $placement->getId(),
            'status' => $entryStatus
        ))->current();
        if ($entry) {
            $item = \Ca\Db\ItemMap::create()->findFiltered([
                'assessmentId' => $entry->getAssessmentId(),
                'scaleId' => $scaleId
            ])->current();
            if ($item) {
                $val = \Ca\Db\EntryMap::create()->findValue($entry->getId(), $item->getId());
                if ($val) return $val->value;
            }
        }
        return '';
    }

    /**
     * @param int $studentId
     * @return Entry
     */
    public function setStudentId($studentId) : Entry
    {
        $this->studentId = $studentId;
        return $this;
    }

    /**
     * return int
     */
    public function getStudentId() : int
    {
        return $this->studentId;
    }

    /**
     * @return \Tk\Db\Map\Model|\Tk\Db\ModelInterface|null|\Uni\Db\UserIface
     */
    public function getStudent()
    {
        if (!$this->_student) {
            try {
                $this->_student = Config::getInstance()->getUserMapper()->find($this->getStudentId());
            } catch (\Exception $e) {}
        }
        return $this->_student;
    }

    /**
     * @param int $assessorId
     * @return Entry
     */
    public function setAssessorId($assessorId) : Entry
    {
        $this->assessorId = $assessorId;
        return $this;
    }

    /**
     * return int
     */
    public function getAssessorId() : int
    {
        return $this->assessorId;
    }

    /**
     * @return \Tk\Db\Map\Model|\Tk\Db\ModelInterface|null|\Uni\Db\UserIface
     */
    public function getAssessor()
    {
        if (!$this->_assessor) {
            try {
                $this->_assessor = Config::getInstance()->getUserMapper()->find($this->getAssessorId());
            } catch (\Exception $e) {}
        }
        return $this->_assessor;
    }

    /**
     * @param string $title
     * @return Entry
     */
    public function setTitle($title) : Entry
    {
        $this->title = $title;
        return $this;
    }

    /**
     * return string
     */
    public function getTitle() : string
    {
        return $this->title;
    }

    /**
     * @param string $assessorName
     * @return Entry
     */
    public function setAssessorName($assessorName) : Entry
    {
        $this->assessorName = $assessorName;
        return $this;
    }

    /**
     * return string
     */
    public function getAssessorName() : string
    {
        return $this->assessorName;
    }

    /**
     * @param string $assessorEmail
     * @return Entry
     */
    public function setAssessorEmail($assessorEmail) : Entry
    {
        $this->assessorEmail = $assessorEmail;
        return $this;
    }

    /**
     * return string
     */
    public function getAssessorEmail() : string
    {
        return $this->assessorEmail;
    }

    /**
     * @param int $absent
     * @return Entry
     */
    public function setAbsent($absent) : Entry
    {
        $this->absent = $absent;
        return $this;
    }

    /**
     * return int
     */
    public function getAbsent() : int
    {
        return $this->absent;
    }

    /**
     * @param float $average
     * @return Entry
     */
    public function setAverage($average) : Entry
    {
        $this->average = $average;
        return $this;
    }

    /**
     * return float
     */
    public function getAverage() : float
    {
        if ($this->average == 0)
            $this->calculateAverage();
        return $this->average;
    }

    /**
     * @param string $notes
     * @return Entry
     */
    public function setNotes($notes) : Entry
    {
        $this->notes = $notes;
        return $this;
    }

    /**
     * return string
     */
    public function getNotes() : string
    {
        return $this->notes;
    }

    /**
     * Get the entry average score.
     *
     * @return float
     * @todo We need to complete this function
     */
    public function calculateAverage()
    {
        return 0.0;
    }

    /**
     * @return array
     */
    public function validate()
    {
        $errors = array();
        $errors = $this->validateAssessmentId($errors);
        $errors = $this->validateSubjectId($errors);

        if (!$this->getStudentId()) {
            $errors['studentId'] = 'Invalid Student ID';
        }

        if (!$this->getTitle()) {
            $errors['title'] = 'Invalid Title';
        }

        if (!$this->getAssessorName()) {
            $errors['assessorName'] = 'Invalid Assessor Name';
        }

        if (!filter_var($this->getAssessorEmail(), FILTER_VALIDATE_EMAIL)) {
            $errors['assessorEmail'] = 'Invalid Assessor Email';
        }

//        if ($this->getAssessorEmail() && !filter_var($this->getAssessorEmail(), FILTER_VALIDATE_EMAIL)) {
//            $errors['assessorEmail'] = 'Invalid Assessor Email';
//        }

        if (!$this->getStatus()) {
            $errors['status'] = 'Invalid Status value';
        }

        return $errors;
    }



    /**
     * Must be Called after the status object is saved.
     * Should return true if the status has changed and the statusChange event should be triggered
     *
     * @param Status $status
     * @return boolean
     * @throws \Exception
     */
    public function hasStatusChanged(Status $status)
    {
        $prevStatusName = $status->getPreviousName();
        switch($status->name) {
            case Entry::STATUS_PENDING:
                if (!$prevStatusName)
                    return true;
                break;
            case Entry::STATUS_APPROVED:
                if (!$prevStatusName || Entry::STATUS_PENDING == $prevStatusName)
                    return true;
                break;
            case Entry::STATUS_AMEND:
                if ($prevStatusName) {
                    return true;
                }
                break;
            case Entry::STATUS_NOT_APPROVED:
                if (Entry::STATUS_PENDING == $prevStatusName)
                    return true;
                break;
        }
        return false;
    }

    /**
     * @param \Bs\Db\Status $status
     * @param CurlyMessage $message
     * @throws \Exception
     */
    public function formatStatusMessage($status, $message)
    {
        $entry = $this;
        $assessment = $entry->getAssessment();
        /** @var MailTemplate $mailTemplate */
        $mailTemplate = $message->get('_mailTemplate');
        if (!$mailTemplate) {
            \Tk\Log::warning('Message has no template: Assessment');
        }

        $placement = $entry->getPlacement();
        if (!$placement->getPlacementType()->isNotifications()) {
            \Tk\Log::warning('PlacementType[' . $placement->getPlacementType()->getName() . '] Notifications Disabled');
        }

        $isReminder = false;
        if ($status->getEvent() == 'message.ca.entry.reminder')
            $isReminder = true;

        if ($isReminder && $mailTemplate->getRecipient()) {
            if ($assessment->getAssessorGroup() == Assessment::ASSESSOR_GROUP_STUDENT &&
                $mailTemplate->getRecipient() != MailTemplate::RECIPIENT_STUDENT) return;
            if ($assessment->getAssessorGroup() == Assessment::ASSESSOR_GROUP_COMPANY &&
                $mailTemplate->getRecipient() != MailTemplate::RECIPIENT_COMPANY) return;
        }

        $msgSubject = $assessment->getName() . ' Entry ' .
            ucfirst($status->getName()) . ' for ' . $placement->getTitle(true) . ' ';
        // '[#'.$entry->getId().'] '

        $message->setSubject($msgSubject);
        $message->setFrom(\Tk\Mail\Message::joinEmail(\Uni\Util\Status::getCourse($status)->getEmail(),
            \Uni\Util\Status::getSubjectName($status)));

        $student = $this->getPlacement()->getUser();
        $mentorList = $this->getConfig()->getUserMapper()->findFiltered(['id' => $this->getConfig()->getUserMapper()->findMentor($student->getId())]);
        /** @var User $mentor */
        $mentor = $mentorList->current();


        // Setup the message vars
        StatusMessage::setStudent($message, $placement->getUser());
        StatusMessage::setSupervisor($message, $placement->getSupervisor());
        StatusMessage::setCompany($message, $placement->getCompany());
        StatusMessage::setPlacement($message, $placement);
        if ($mentor)
            StatusMessage::setMentor($message, $mentor);

        // A`dd entry details
        $message->set('_assessment', $assessment);
        $message->set('assessment::id', $assessment->getId());
        $message->set('assessment::name', $assessment->getName());
        $message->set('assessment::description', $assessment->getDescription());
        $message->set('assessment::placementTypes', $assessment->getPlacementTypeName());

        $message->set('entry::id', $entry->getId());
        $message->set('entry::title', $entry->getTitle());
        $message->set('entry::assessor', $entry->getAssessorName());
        $message->set('entry::status', $entry->getStatus());
        $message->set('entry::notes', nl2br($entry->getNotes(), true));

        // Add assessment blocks
        $list = \Ca\Db\AssessmentMap::create()->findFiltered(array('courseId' => $placement->getSubject()->getCourseId()));
        /* @var \Ca\Db\Assessment $assess */
        foreach($list as $assess) {
            $key = $assess->getNameKey();
            if ($entry->getAssessmentId() == $assess->getId()) {
                $message->set($key, true);
            } else {
                $message->set('not'.$key, true);
            }
        }

        // Attach PDF
        if (strstr($mailTemplate->getTemplate(), '{entry::attachPdf}') !== false) {
            $watermark = '';
            $pdf = \Ca\Util\Pdf\Entry::create($entry, $watermark);
            $message->addStringAttachment($pdf->getPdfAttachment(), $pdf->getFilename());
        }
        
        switch ($mailTemplate->getRecipient()) {
            case \App\Db\MailTemplate::RECIPIENT_STUDENT:
                $student = $placement->getUser();
                if ($student && $student->getEmail()) {
                    $message->addTo(\Tk\Mail\Message::joinEmail($student->getEmail(), $student->getName()));
                    $message->set('recipient::email', $student->getEmail());
                    $message->set('recipient::name', $student->getName());
                }
                break;
            case \App\Db\MailTemplate::RECIPIENT_COMPANY:
                $company = $placement->getCompany();
                if ($company && $company->getEmail()) {
                    $message->addTo(\Tk\Mail\Message::joinEmail($company->getEmail(), $company->getName()));
                    $message->set('recipient::email', $company->getEmail());
                    $message->set('recipient::name', $company->getName());
                }
                break;
            case \App\Db\MailTemplate::RECIPIENT_SUPERVISOR:
                $supervisor = $placement->getSupervisor();
                if ($supervisor && $supervisor->getEmail()) {
                    $message->addTo(\Tk\Mail\Message::joinEmail($supervisor->getEmail(), $supervisor->getName()));
                    $message->set('recipient::email', $supervisor->getEmail());
                    $message->set('recipient::name', $supervisor->getName());
                }
                break;
            case \App\Db\MailTemplate::RECIPIENT_STAFF:
                $subject = \Uni\Util\Status::getSubject($status);
                $staffList = $subject->getCourse()->getUsers();
                if (count($staffList)) {
                    /** @var \App\Db\User $s */
                    foreach ($staffList as $s) {
                        $message->addBcc(\Tk\Mail\Message::joinEmail($s->getEmail(), $s->getName()));
                    }
                    $message->addTo(\Tk\Mail\Message::joinEmail($subject->getCourse()->getEmail(), \Uni\Util\Status::getSubjectName($status)));
                    $message->set('recipient::email', $subject->getCourse()->getEmail());
                    $message->set('recipient::name', \Uni\Util\Status::getSubjectName($status));
                }
                break;
            case MailTemplate::RECIPIENT_MENTOR:
                //if (count($mentorList) && $assessment->getAssessorGroup() != Assessment::ASSESSOR_GROUP_STUDENT) {
                if (count($mentorList)) {
                    $message->set('sig', '');
                    $message->setFrom($placement->getSubject()->getInstitution()->getData()->get('mentor.coordinator.email', $placement->getSubject()->getInstitution()->getEmail()));
                    /** @var User $s */
                    foreach ($mentorList as $s) {
                        $message->addBcc(\Tk\Mail\Message::joinEmail($s->getEmail(), $s->getName()));
                    }
                    $message->addTo(\Tk\Mail\Message::joinEmail($mentor->getEmail(), $mentor->getName()));
                    $message->set('recipient::email', $mentor->getEmail());
                    $message->set('recipient::name', $mentor->getName());
                }
                break;
        }

        // This is for all recipients only
        if (!$isReminder || $mailTemplate->getRecipient()) { return; }

        switch ($assessment->getAssessorGroup()) {
            case Assessment::ASSESSOR_GROUP_STUDENT:
                $student = $placement->getUser();
                if ($student && $student->getEmail()) {
                    $message->addTo(\Tk\Mail\Message::joinEmail($student->getEmail(), $student->getName()));
                    $message->set('recipient::email', $student->getEmail());
                    $message->set('recipient::name', $student->getName());
                }
                break;
            case Assessment::ASSESSOR_GROUP_COMPANY:
                $company = $placement->getCompany();
                // TODO: We could also send one to the placement supervisor directly
                if ($company && $company->getEmail()) {
                    $message->addTo(\Tk\Mail\Message::joinEmail($company->getEmail(), $company->getName()));
                    $message->set('recipient::email', $company->getEmail());
                    $message->set('recipient::name', $company->getName());
                }
                break;
        }
    }

    /**
     * @return string|Template
     */
    public function getPendingIcon()
    {
        $editUrl = \Uni\Uri::createSubjectUrl('/ca/entryEdit.html')->set('entryId', $this->getId());
        if (!$this->getId()) {
            $editUrl = \Uni\Uri::createSubjectUrl('/ca/entryEdit.html')->set('assessmentId', $this->getAssessmentId())->
            set('studentId', $this->getStudentId())->set('placementId', $this->getPlacementId());
        }
        return sprintf('<a href="%s"><div class="status-icon bg-secondary"><i class="'.$this->getAssessment()->getIcon().'"></i></div></a>', htmlentities($editUrl));
    }

    /**
     * @return string|Template
     * @throws \Exception
     */
    public function getPendingHtml()
    {
        $editUrl = \Uni\Uri::createSubjectUrl('/ca/entryEdit.html')->set('entryId', $this->getId());
        $from = '';

        $userName = $this->getPlacement()->getUser()->getName();
        if ($this->getPlacement()) {
            $from = 'from <em>' . htmlentities($this->getPlacement()->getCompany()->getName()) . '</em>';
        }

        $html = sprintf('<div class="status-placement"><div><em>%s</em> %s submitted a %s Assessment Entry for <em>%s</em></div>
  <div class="status-actions">
    <a href="%s" class="edit"><i class="fa fa-pencil"></i> Edit</a>
   <!--  |
    <a href="#" class="view"><i class="fa fa-eye"></i> View</a> |
    <a href="#" class="approve"><i class="fa fa-check"></i> Approve</a> |
    <a href="#" class="reject"><i class="fa fa-times"></i> Reject</a> |
    <a href="#" class="email"><i class="fa fa-envelope"></i> Email</a>
    -->
  </div>
</div>',
            htmlentities($this->getAssessorName()), $from, htmlentities($this->getAssessment()->getName()), htmlentities($userName), htmlentities($editUrl));

        return $html;
    }


    /**
     * @return string
     * @throws \Exception
     */
    public function getLabel()
    {
        return $this->getAssessment()->getName() . ' ' . \Tk\ObjectUtil::basename($this->getCurrentStatus()->getFkey());
    }


}
