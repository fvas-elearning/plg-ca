<?php
namespace Ca\Db;

use Bs\Db\Mapper;
use Tk\DataMap\Db;
use Tk\DataMap\Form;
use Tk\Db\Filter;
use Tk\Db\Map\ArrayObject;
use Tk\Db\Tool;

/**
 * @author Mick Mifsud
 * @created 2019-11-06
 * @link http://tropotek.com.au/
 * @license Copyright 2019 Tropotek
 */
class EntryMap extends Mapper
{

    /**
     * @return \Tk\DataMap\DataMap
     */
    public function getDbMap()
    {
        if (!$this->dbMap) { 
            $this->setTable('ca_entry');

            $this->dbMap = new \Tk\DataMap\DataMap();
            $this->dbMap->addPropertyMap(new Db\Integer('id'), 'key');
            $this->dbMap->addPropertyMap(new Db\Integer('assessmentId', 'assessment_id'));
            $this->dbMap->addPropertyMap(new Db\Integer('subjectId', 'subject_id'));
            $this->dbMap->addPropertyMap(new Db\Integer('studentId', 'student_id'));
            $this->dbMap->addPropertyMap(new Db\Integer('assessorId', 'assessor_id'));
            $this->dbMap->addPropertyMap(new Db\Integer('placementId', 'placement_id'));
            $this->dbMap->addPropertyMap(new Db\Text('title'));
            $this->dbMap->addPropertyMap(new Db\Text('assessorName', 'assessor_name'));
            $this->dbMap->addPropertyMap(new Db\Text('assessorEmail', 'assessor_email'));
            $this->dbMap->addPropertyMap(new Db\Integer('absent'));
            $this->dbMap->addPropertyMap(new Db\Decimal('average'));
            $this->dbMap->addPropertyMap(new Db\Text('status'));
            $this->dbMap->addPropertyMap(new Db\Text('notes'));
            $this->dbMap->addPropertyMap(new Db\Date('modified'));
            $this->dbMap->addPropertyMap(new Db\Date('created'));

        }
        return $this->dbMap;
    }

    /**
     * @return \Tk\DataMap\DataMap
     */
    public function getFormMap()
    {
        if (!$this->formMap) {
            $this->formMap = new \Tk\DataMap\DataMap();
            $this->formMap->addPropertyMap(new Form\Integer('id'), 'key');
            $this->formMap->addPropertyMap(new Form\Integer('assessmentId'));
            $this->formMap->addPropertyMap(new Form\Integer('subjectId'));
            $this->formMap->addPropertyMap(new Form\Integer('studentId'));
            $this->formMap->addPropertyMap(new Form\Integer('assessorId'));
            $this->formMap->addPropertyMap(new Form\Integer('placementId'));
            $this->formMap->addPropertyMap(new Form\Text('title'));
            $this->formMap->addPropertyMap(new Form\Text('assessorName'));
            $this->formMap->addPropertyMap(new Form\Text('assessorEmail'));
            $this->formMap->addPropertyMap(new Form\Integer('absent'));
            $this->formMap->addPropertyMap(new Form\Decimal('average'));
            $this->formMap->addPropertyMap(new Form\Text('status'));
            $this->formMap->addPropertyMap(new Form\Text('notes'));
            $this->formMap->addPropertyMap(new Form\Date('modified'));
            $this->formMap->addPropertyMap(new Form\Date('created'));

        }
        return $this->formMap;
    }

    /**
     * @param array|Filter $filter
     * @param Tool $tool
     * @return ArrayObject|Entry[]
     * @throws \Exception
     */
    public function findFiltered($filter, $tool = null)
    {
        return $this->selectFromFilter($this->makeQuery(\Tk\Db\Filter::create($filter)), $tool);
    }

    /**
     * @param Filter $filter
     * @return Filter
     */
    public function makeQuery(Filter $filter)
    {
        $filter->appendFrom('%s a', $this->quoteParameter($this->getTable()));

        if (!empty($filter['keywords'])) {
            $kw = '%' . $this->escapeString($filter['keywords']) . '%';
            $w = '';
            //$w .= sprintf('a.name LIKE %s OR ', $this->quote($kw));
            if (is_numeric($filter['keywords'])) {
                $id = (int)$filter['keywords'];
                $w .= sprintf('a.id = %d OR ', $id);
            }
            if ($w) $filter->appendWhere('(%s) AND ', substr($w, 0, -3));
        }

        if (!empty($filter['id'])) {
            $w = $this->makeMultiQuery($filter['id'], 'a.id');
            if ($w) $filter->appendWhere('(%s) AND ', $w);
        }
        if (!empty($filter['assessmentId'])) {
            $w = $this->makeMultiQuery($filter['assessmentId'], 'a.assessment_id');
            if ($w) $filter->appendWhere('(%s) AND ', $w);
        }
        if (!empty($filter['subjectId'])) {
            $filter->appendWhere('a.subject_id = %s AND ', (int)$filter['subjectId']);
        }
        if (!empty($filter['studentId'])) {
            $filter->appendWhere('a.student_id = %s AND ', (int)$filter['studentId']);
        }
        if (!empty($filter['assessorId'])) {
            $filter->appendWhere('a.assessor_id = %s AND ', (int)$filter['assessorId']);
        }
        if (!empty($filter['placementId'])) {
            $filter->appendWhere('a.placement_id = %s AND ', (int)$filter['placementId']);
        }
        if (!empty($filter['title'])) {
            $filter->appendWhere('a.title = %s AND ', $this->quote($filter['title']));
        }
        if (!empty($filter['assessorName'])) {
            $filter->appendWhere('a.assessor_name = %s AND ', $this->quote($filter['assessorName']));
        }
        if (!empty($filter['assessorEmail'])) {
            $filter->appendWhere('a.assessor_email = %s AND ', $this->quote($filter['assessorEmail']));
        }
        if (!empty($filter['absent'])) {
            $filter->appendWhere('a.absent = %s AND ', (int)$filter['absent']);
        }
        if (!empty($filter['average'])) {
            $filter->appendWhere('a.average = %s AND ', (float)$filter['average']);
        }
        if (!empty($filter['status'])) {
            $w = $this->makeMultiQuery($filter['status'], 'a.status');
            if ($w) $filter->appendWhere('(%s) AND ', $w);
        }

        if (!empty($filter['exclude'])) {
            $w = $this->makeMultiQuery($filter['exclude'], 'a.id', 'AND', '!=');
            if ($w) $filter->appendWhere('(%s) AND ', $w);
        }

        return $filter;
    }


    /**
     * @param int $entryId
     * @param int $itemId
     * @return array|\stdClass
     * @throws \Exception
     */
    public function findValue($entryId, $itemId = 0)
    {
        $st = null;
        if ($itemId) {
            $st = $this->getDb()->prepare('SELECT * FROM ca_value a WHERE a.entry_id = ? AND a.item_id = ?');
            $st->bindParam(1, $entryId);
            $st->bindParam(2, $itemId);
        } else {
            $st = $this->getDb()->prepare('SELECT * FROM ca_value a WHERE a.entry_id = ?');
            $st->bindParam(1, $entryId);
        }
        $st->execute();
        $arr = $st->fetchAll();
        if($itemId) return current($arr);
        return $arr;
    }

    /**
     * @param int $entryId
     * @param int $itemId
     * @param string $value
     * @throws \Exception
     */
    public function saveValue($entryId, $itemId, $value)
    {
        /** @var Item $item */
        //$item = $this->find($itemId);
        $item = ItemMap::create()->find($itemId);
        if ($item && $item->getScale()->getType() == Scale::TYPE_VALUE) {
            $max = $item->getScale()->getMaxValue();
            if ($value < 0) $value = 0;
            if ($value > $max) $value = $max;
        }

        if ($this->hasValue($entryId, $itemId)) {
            $st = $this->getDb()->prepare('UPDATE ca_value SET value = ? WHERE entry_id = ? AND item_id = ? ');
        } else {
            $st = $this->getDb()->prepare('INSERT INTO ca_value (value, entry_id, item_id) VALUES (?, ?, ?)');
        }
        $st->bindParam(1, $value);
        $st->bindParam(2, $entryId);
        $st->bindParam(3, $itemId);
        $st->execute();
    }

    /**
     * @param int $entryId
     * @param int $itemId
     * @throws \Exception
     */
    public function removeValue($entryId, $itemId = null)
    {
        $st = $this->getDb()->prepare('DELETE FROM ca_value WHERE entry_id = ?');
        $st->bindParam(1, $entryId);
        if ($itemId !== null) {
            $st = $this->getDb()->prepare('DELETE FROM ca_value WHERE entry_id = ? AND item_id = ?');
            $st->bindParam(1, $entryId);
            $st->bindParam(2, $itemId);
        }
        $st->execute();
    }

    /**
     * Does the value record exist
     *
     * @param int $entryId
     * @param int $itemId
     * @return bool
     * @throws \Exception
     */
    public function hasValue($entryId, $itemId)
    {
        $val = $this->findValue($entryId, $itemId);
        return $val != null;
    }


    /**
     * Get a list of placements with no assessments
     *
     * @param Assessment $assessment
     * @param \Uni\Db\SubjectIface $subject
     * @param \DateTime|null $now
     * @return false|\PDOStatement|\Tk\Db\PDOStatement
     * @throws \Tk\Db\Exception
     */
    public function findReminders(Assessment $assessment, \Uni\Db\SubjectIface $subject, ?\DateTime $now = null)
    {
        if (!$now)
            $now = \Tk\Date::create();
        $now = $now->format(\Tk\Date::FORMAT_ISO_DATETIME);

        $placementTypeIds = $assessment->getPlacementTypes()->toArray('id');
        $placementTypeIdSql = \Tk\Db\Mapper::makeMultipleQuery($placementTypeIds, 'a2.placement_type_id');
        if ($placementTypeIdSql)
            $placementTypeIdSql = 'AND (' . $placementTypeIdSql . ')';
        $statusSql = \Tk\Db\Mapper::makeMultipleQuery($assessment->getPlacementStatus(), 'a2.status');
        if ($statusSql)
            $statusSql = 'AND (' . $statusSql . ')';
        $maxReminder = $assessment->getReminderRepeatCycles()+1;

        $sql = sprintf("SELECT *
FROM (
        SELECT DISTINCT a1.*
        FROM placement a1
        LEFT JOIN ca_entry ce ON (a1.id = ce.placement_id AND ce.assessment_id = %s)
        WHERE
          ce.id IS NULL
          AND DATE(a1.date_start) > '2020-01-01' -- So older placements are not included
          AND DATE(DATE_ADD(a1.date_end, INTERVAL 7 DAY)) <= DATE(%s)
        ORDER BY a1.date_end DESC
    ) a,
    (
         SELECT a2.id as 'placement_id', %s as 'assessment_id', IFNULL(COUNT(b2.date), 0) as 'reminder_count', MAX(b2.date) as 'last_sent'
         FROM placement a2 LEFT JOIN ca_reminder b2 ON (a2.id = b2.placement_id AND b2.assessment_id = %s)
         WHERE
           a2.subject_id = %s
           %s
           %s
         GROUP BY a2.id
    ) b
WHERE a.id = b.placement_id 
      AND b.reminder_count <= %s
ORDER BY a.date_end DESC
",
            $assessment->getId(), $this->quote($now), $assessment->getId(), $assessment->getId(),
            $subject->getId(), $placementTypeIdSql, $statusSql,
            $maxReminder
        );

        return $this->getConfig()->getDb()->query($sql);
    }

    /**
     * @param int $assessmentId
     * @param int $placementId
     * @param \DateTime|null $now
     * @return bool
     * @throws \Tk\Db\Exception
     */
    public function addReminderLog($assessmentId, $placementId, ?\DateTime $now = null)
    {
        if (!$now)
            $now = \Tk\Date::create();
        $now = $now->format(\Tk\Date::FORMAT_ISO_DATETIME);
        $stmt = $this->getDb()->prepare('INSERT INTO ca_reminder (assessment_id, placement_id, date) VALUES (?, ?, ?)');
        return $stmt->execute(array($assessmentId, $placementId, $now));
    }

    /**
     * return the reminder logs, the first one in the array is the latest log
     *
     * @param int $assessmentId
     * @param int $placementId
     * @return array
     * @throws \Exception
     */
    public function getReminderLog($assessmentId, $placementId)
    {
        $stmt = $this->getDb()->prepare('SELECT * FROM ca_reminder WHERE assessment_id = ? AND placement_id = * ORDER BY date DESC');
        $stmt->execute(array($assessmentId, $placementId));
        return $stmt->fetchAll();
    }
}