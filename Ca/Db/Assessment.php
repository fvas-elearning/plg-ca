<?php
namespace Ca\Db;

use App\Db\Placement;
use Bs\Db\Traits\TimestampTrait;
use Uni\Db\Traits\CourseTrait;

/**
 * @author Mick Mifsud
 * @created 2019-10-31
 * @link http://tropotek.com.au/
 * @license Copyright 2019 Tropotek
 */
class Assessment extends \Tk\Db\Map\Model implements \Tk\ValidInterface
{
    use CourseTrait;
    use TimestampTrait;

    const ASSESSOR_GROUP_STUDENT = 'student';
    const ASSESSOR_GROUP_COMPANY = 'company';
    //const ASSESSOR_GROUP_STAFF = 'staff';           // TODO: this is not full implemented as yet

    /**
     * @var int
     */
    public $id = 0;

    /**
     * @var string
     */
    public $uid = '';

    /**
     * @var int
     */
    public $courseId = 0;

    /**
     * @var string
     */
    public $name = '';

    /**
     * @var string
     */
    public $icon = 'fa fa-rebel';

    /**
     * @var array
     */
    public $placementStatus = array();

    /**
     * @var string
     */
    public $assessorGroup = 'student';

    /**
     * @var bool
     */
    public $includeZero = false;

    /**
     * @var bool
     */
    public $enableCheckbox = false;

    /**
     * @var bool
     */
    public $enableReminder = false;

    /**
     * @var int
     */
    public $reminderInitialDays = 7;

    /**
     * @var int
     */
    public $reminderRepeatDays = 28;

    /**
     * @var int
     */
    public $reminderRepeatCycles = 4;

    /**
     * @var string
     */
    public $description = '';

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
     * Assessment
     */
    public function __construct()
    {
        $this->_TimestampTrait();
    }

    /**
     * save to DB
     */
    public function save()
    {
        if (!$this->getUid())
            $this->setUid($this->getVolatileId());
        parent::save();
    }

    /**
     * @param string $uid
     * @return Assessment
     */
    public function setUid($uid) : Assessment
    {
        $this->uid = $uid;
        return $this;
    }

    /**
     * return string
     */
    public function getUid() : string
    {
        return $this->uid;
    }

    /**
     * @param string $name
     * @return Assessment
     */
    public function setName($name) : Assessment
    {
        $this->name = $name;
        return $this;
    }

    /**
     * return string
     */
    public function getName() : string
    {
        return $this->name;
    }

    /**
     * Return a key that is used for the mail templates
     *
     * @return string|string[]|null
     */
    public function getNameKey()
    {
        return preg_replace('/[^a-z0-9-_]/i', '', $this->getName());
    }

    /**
     * @param string $icon
     * @return Assessment
     */
    public function setIcon($icon) : Assessment
    {
        $this->icon = $icon;
        return $this;
    }

    /**
     * return string
     */
    public function getIcon() : string
    {
        return $this->icon;
    }

    /**
     * @param array $placementStatus
     * @return Assessment
     */
    public function setPlacementStatus(array $placementStatus) : Assessment
    {
        $this->placementStatus = $placementStatus;
        return $this;
    }

    /**
     * return array|null
     */
    public function getPlacementStatus() : ?array
    {
        return $this->placementStatus;
    }

    /**
     * @param null|\Tk\Db\Tool $tool
     * @return \Tk\Db\Map\ArrayObject|\App\Db\PlacementType[]
     * @throws \Exception
     */
    public function getPlacementTypes($tool = null)
    {
        $arr = AssessmentMap::create()->findPlacementTypes($this->getId());
        $list = \App\Db\PlacementTypeMap::create()->findFiltered(array('id' => $arr), $tool);
        return $list;
    }

    /**
     * @param string $sep (optional)
     * @return string
     * @throws \Exception
     */
    public function getPlacementTypeName($sep = ', ')
    {
        $list = $this->getPlacementTypes();
        $str = '';
        foreach ($list as $placementType) {
            $str .= $sep . $placementType->getName();
        }
        $str = trim($str, $sep);
        return $str;
    }

    /**
     * @param string $assessorGroup
     * @return Assessment
     */
    public function setAssessorGroup($assessorGroup) : Assessment
    {
        $this->assessorGroup = $assessorGroup;
        return $this;
    }

    /**
     * return string
     */
    public function getAssessorGroup() : string
    {
        return $this->assessorGroup;
    }

    /**
     * @param bool $includeZero
     * @return Assessment
     */
    public function setIncludeZero($includeZero) : Assessment
    {
        $this->includeZero = $includeZero;
        return $this;
    }

    /**
     * return bool
     */
    public function isIncludeZero() : bool
    {
        return $this->includeZero;
    }

    /**
     * @return bool
     */
    public function isEnableCheckbox(): bool
    {
        return $this->enableCheckbox;
    }

    /**
     * @param bool $enableCheckbox
     * @return Assessment
     */
    public function setEnableCheckbox($enableCheckbox): Assessment
    {
        $this->enableCheckbox = $enableCheckbox;
        return $this;
    }

    /**
     * @return bool
     */
    public function isEnableReminder(): bool
    {
        return $this->enableReminder;
    }

    /**
     * @param bool $enableReminder
     * @return Assessment
     */
    public function setEnableReminder(bool $enableReminder): Assessment
    {
        $this->enableReminder = $enableReminder;
        return $this;
    }

    /**
     * @return int
     */
    public function getReminderInitialDays(): int
    {
        return $this->reminderInitialDays;
    }

    /**
     * @param int $reminderInitialDays
     * @return Assessment
     */
    public function setReminderInitialDays(int $reminderInitialDays): Assessment
    {
        $this->reminderInitialDays = $reminderInitialDays;
        return $this;
    }

    /**
     * @return int
     */
    public function getReminderRepeatDays(): int
    {
        return $this->reminderRepeatDays;
    }

    /**
     * @param int $reminderRepeatDays
     * @return Assessment
     */
    public function setReminderRepeatDays(int $reminderRepeatDays): Assessment
    {
        $this->reminderRepeatDays = $reminderRepeatDays;
        return $this;
    }

    /**
     * @return int
     */
    public function getReminderRepeatCycles(): int
    {
        return $this->reminderRepeatCycles;
    }

    /**
     * @param int $reminderRepeatCycles
     * @return Assessment
     */
    public function setReminderRepeatCycles(int $reminderRepeatCycles): Assessment
    {
        $this->reminderRepeatCycles = $reminderRepeatCycles;
        return $this;
    }

    /**
     * @param string $description
     * @return Assessment
     */
    public function setDescription($description) : Assessment
    {
        $this->description = $description;
        return $this;
    }

    /**
     * return string
     */
    public function getDescription() : string
    {
        return $this->description;
    }

    /**
     * @param string $notes
     * @return Assessment
     */
    public function setNotes($notes) : Assessment
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
     * Check if this assessment is open with the given placement status
     *
     * @param string|array $status
     * @return bool
     */
    public function hasStatus($status)
    {
        if (!is_array($status)) $status = array($status);
        return in_array($this->getPlacementStatus(), $status);
    }

    /**
     * Is an assessment is active for a subject
     *
     * @param int $subjectId
     * @return bool
     */
    public function isActive($subjectId)
    {
        if ($subjectId instanceof \Uni\Db\SubjectIface) $subjectId = $subjectId->getId();
        return AssessmentMap::create()->hasSubject($subjectId, $this->getId());
    }

    /**
     * Use this to test if the assessment:
     *  - Assessment is saved
     *  - if has placement:
     *     - Assessment is enabled for that placement type
     *     - Assessment is active in that placement subject
     *
     * @param \App\Db\Placement $placement (optional)
     * @return bool
     */
    public function isAvailable($placement = null)
    {
        if (!$this->getId()) return false;
        $b = true;
        if ($placement) {
            if ($placement->hasStatus(array(Placement::STATUS_DRAFT, Placement::STATUS_CANCELLED, Placement::STATUS_NOT_APPROVED, Placement::STATUS_PENDING, Placement::STATUS_AMEND)))
                return false;
            $b &= $this->isActive($placement->getSubjectId());
            $b &= AssessmentMap::create()->hasPlacementType($this->getId(), $placement->getPlacementTypeId());
        }
        return $b;
    }

    /**
     * @param \App\Db\Placement $placement
     * @param \Uni\Db\User $user (null = public user)
     * @return bool
     * @throws \Exception
     */
    public function canReadEntry($placement, $user = null)
    {
        if (!$this->getId() || !$this->isActive($placement->getSubjectId()) || !$this->isAvailable($placement)) return false;
        if ($user) {        // Only logged in users can read entries at this stage
            if ($user->isStaff() || $user->isStudent()) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param \App\Db\Placement $placement
     * @param \Uni\Db\User $user (null = public user)
     * @return bool
     * @throws \Exception
     */
    public function canWriteEntry($placement, $user=null)
    {
        if (!$this->getId() || !$this->isActive($placement->getSubjectId()) || !$this->isAvailable($placement)) {
            return false;
        }
        if ($user) {
            if ($user->isStaff()) {
                return true;
            } else {    // Student
                $entry = $this->findEntry($placement);
                if ($entry && $entry->hasStatus(array(\Ca\Db\Entry::STATUS_PENDING, \Ca\Db\Entry::STATUS_AMEND))) {
                    return true;
                }
                if (!$this->hasStatus($placement->getStatus())) {
                    return false;
                }
            }
        } else {
            if ($this->getAssessorGroup() == self::ASSESSOR_GROUP_COMPANY && $this->hasStatus($placement->getStatus())) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param \App\Db\Placement $placement
     * @return \Tk\Db\Map\Model|\App\Db\Placement|null
     * @throws \Exception
     */
    public function findEntry($placement)
    {
        $filter = array(
            'assessmentId' => $this->getId(),
            'placementId' => $placement->getId()
        );
        return \Ca\Db\EntryMap::create()->findFiltered($filter)->current();
    }

    /**
     * If the assessor group is student then this is a self assessment
     * Self assessment forms should be shown after the student submit their
     *
     * @return bool
     */
    public function isSelfAssessment()
    {
        return ($this->getAssessorGroup() == self::ASSESSOR_GROUP_STUDENT);
    }

    /**
     * return the status list for a select field
     * @param null|string $current
     * @return array
     */
    public static function getAssessorGroupList($current = null)
    {
        $arr = \Tk\Form\Field\Select::arrayToSelectList(\Tk\ObjectUtil::getClassConstants(__CLASS__, 'ASSESSOR_GROUP'));
        if (is_string($current)) {
            $arr2 = array();
            foreach ($arr as $k => $v) {
                if ($v == $current) {
                    $arr2[$k.' (Current)'] = $v;
                } else {
                    $arr2[$k] = $v;
                }
            }
            $arr = $arr2;
        }
        return $arr;
    }

    /**
     * create a URL for the public entry submissions
     *
     * @param string $placementHash
     * @return string|\Tk\Uri|\Uni\Uri
     */
    public function getPublicUrl($placementHash)
    {
        return \Uni\Uri::createInstitutionUrl('/assessment.html', $this->getCourse()->getInstitution())
            ->set('h', $placementHash)
            ->set('assessmentId', $this->getId());
    }

    /**
     * @return array
     */
    public function validate()
    {
        $errors = array();
        $errors = $this->validateCourseId($errors);

        if (!$this->getName()) {
            $errors['name'] = 'Invalid value: name';
        }

        if (!$this->getIcon()) {
            $errors['icon'] = 'Invalid value: icon';
        }

        if (!$this->isSelfAssessment() && (!$this->getPlacementStatus() || !count($this->getPlacementStatus()))) {
            $errors['statusAvailable'] = 'Invalid value: statusAvailable';
        }

        if (!$this->getAssessorGroup()) {
            $errors['assessorGroup'] = 'Invalid value: assessorGroup';
        }

        if ($this->isEnableReminder()) {
            if (!$this->getReminderInitialDays()) {
                $errors['reminderInitialDays'] = 'Please set the number of days after placement completion that the first reminder is sent. (Default: 7)';
            }
            if (!$this->getReminderRepeatDays()) {
                $errors['reminderRepeatDays'] = 'Please set the number of days after the first reminder is sent before sending subsequent reminders. (Default: 28)';
            }
            if (!$this->getReminderRepeatCycles()) {
                $errors['reminderRepeatCycles'] = 'Please set the number of time that a reminder message is sent after the initial reminder. (Default: 4)';
            }
        }

        return $errors;
    }


    /**
     * @param $subjectId
     * @return bool
     * @deprecated Use isActive($subjectId)
     */
    public function isPublished($subjectId)
    {
        return $this->isActive($subjectId);
//        if ($subjectId instanceof \Uni\Db\SubjectIface) $subjectId = $subjectId->getId();
//        return AssessmentMap::create()->hasSubject($subjectId, $this->getId());
    }
}
