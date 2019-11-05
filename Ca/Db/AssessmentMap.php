<?php
namespace Ca\Db;

use Tk\Db\Tool;
use Tk\Db\Map\ArrayObject;
use Tk\DataMap\Db;
use Tk\DataMap\Form;
use Bs\Db\Mapper;
use Tk\Db\Filter;
use Tk\Db\Exception;

/**
 * @author Mick Mifsud
 * @created 2019-10-31
 * @link http://tropotek.com.au/
 * @license Copyright 2019 Tropotek
 */
class AssessmentMap extends Mapper
{

    /**
     * @return \Tk\DataMap\DataMap
     */
    public function getDbMap()
    {
        if (!$this->dbMap) { 
            $this->setTable('ca_assessment');

            $this->dbMap = new \Tk\DataMap\DataMap();
            $this->dbMap->addPropertyMap(new Db\Integer('id'), 'key');
            $this->dbMap->addPropertyMap(new Db\Text('uid'));
            $this->dbMap->addPropertyMap(new Db\Integer('courseId', 'course_id'));
            $this->dbMap->addPropertyMap(new Db\Text('name'));
            $this->dbMap->addPropertyMap(new Db\Text('icon'));
            $this->dbMap->addPropertyMap(new Db\ArrayObject('statusAvailable', 'status_available'));
            $this->dbMap->addPropertyMap(new Db\Text('assessorGroup', 'assessor_group'));
            $this->dbMap->addPropertyMap(new Db\Boolean('multi'));
            $this->dbMap->addPropertyMap(new Db\Boolean('includeZero', 'include_zero'));
            //$this->dbMap->addPropertyMap(new Db\Date('publishResult', 'publish_result'));
            $this->dbMap->addPropertyMap(new Db\Text('description'));
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
            $this->formMap->addPropertyMap(new Form\Text('uid'));
            $this->formMap->addPropertyMap(new Form\Integer('courseId'));
            $this->formMap->addPropertyMap(new Form\Text('name'));
            $this->formMap->addPropertyMap(new Form\Text('icon'));
            $this->formMap->addPropertyMap(new Form\ObjectMap('statusAvailable'));
            $this->formMap->addPropertyMap(new Form\Text('assessorGroup'));
            $this->formMap->addPropertyMap(new Form\Boolean('multi'));
            $this->formMap->addPropertyMap(new Form\Boolean('includeZero'));
            //$this->formMap->addPropertyMap(new Form\Date('publishResult'));
            $this->formMap->addPropertyMap(new Form\Text('description'));
            $this->formMap->addPropertyMap(new Form\Text('notes'));
            $this->formMap->addPropertyMap(new Form\Date('modified'));
            $this->formMap->addPropertyMap(new Form\Date('created'));

        }
        return $this->formMap;
    }

    /**
     * @param array|Filter $filter
     * @param Tool $tool
     * @return ArrayObject|Assessment[]
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
            $w .= sprintf('a.name LIKE %s OR ', $this->quote($kw));
            if (is_numeric($filter['keywords'])) {
                $id = (int)$filter['keywords'];
                $w .= sprintf('a.id = %d OR ', $id);
            }
            if ($w) $filter->appendWhere('(%s) AND ', substr($w, 0, -3));
        }

        if (!empty($filter['id'])) {
            $filter->appendWhere('a.id = %s AND ', (int)$filter['id']);
        }
        if (!empty($filter['uid'])) {
            $filter->appendWhere('a.uid = %s AND ', $this->quote($filter['uid']));
        }
        if (!empty($filter['courseId'])) {
            $filter->appendWhere('a.course_id = %s AND ', (int)$filter['courseId']);
        } else if (!empty($filter['profileId'])) {
            $filter->appendWhere('a.course_id = %s AND ', (int)$filter['profileId']);
        }
        if (!empty($filter['name'])) {
            $filter->appendWhere('a.name = %s AND ', $this->quote($filter['name']));
        }
        if (!empty($filter['icon'])) {
            $filter->appendWhere('a.icon = %s AND ', $this->quote($filter['icon']));
        }
        if (!empty($filter['statusAvailable'])) {
            $w = $this->makeMultiQuery($filter['statusAvailable'], 'a.status_available', 'OR', 'LIKE');
            if ($w) $filter->appendWhere('(%s) AND ', $w);
        }
        if (!empty($filter['assessorGroup'])) {
            $w = $this->makeMultiQuery($filter['assessorGroup'], 'a.assessor_group');
            if ($w) $filter->appendWhere('(%s) AND ', $w);
        }
        if (!empty($filter['multi'])) {
            $filter->appendWhere('a.multi = %s AND ', (int)$filter['multi']);
        }
        if (!empty($filter['includeZero'])) {
            $filter->appendWhere('a.include_zero = %s AND ', (int)$filter['includeZero']);
        }

        if (!empty($filter['placementTypeId'])) {
            $filter->appendFrom(' LEFT JOIN %s b ON (a.id = b.assessment_id) ',
                $this->quoteTable('ca_assessment_placement_type'));
            $filter->appendWhere('b.placement_type_id = %s AND ', (int)$filter['placementTypeId']);
        }

        if (!empty($filter['exclude'])) {
            $w = $this->makeMultiQuery($filter['exclude'], 'a.id', 'AND', '!=');
            if ($w) $filter->appendWhere('(%s) AND ', $w);
        }

        return $filter;
    }




    // Link to placement types

    /**
     * @param int $assessmentId
     * @param int $placementTypeId
     * @return boolean
     */
    public function hasPlacementType($assessmentId, $placementTypeId)
    {
        try {
            $stm = $this->getDb()->prepare('SELECT * FROM ca_assessment_placement_type WHERE assessment_id = ? AND placement_type_id = ?');
            $stm->bindParam(1, $assessmentId);
            $stm->bindParam(2, $placementTypeId);
            $stm->execute();
            return ($stm->rowCount() > 0);
        } catch (Exception $e) {}
        return false;
    }

    /**
     * @param int $assessmentId
     * @param int $placementTypeId (optional) If null all are to be removed
     */
    public function removePlacementType($assessmentId, $placementTypeId = null)
    {
        try {
            $stm = $this->getDb()->prepare('DELETE FROM ca_assessment_placement_type WHERE assessment_id = ?');
            $stm->bindParam(1, $assessmentId);
            if ($placementTypeId) {
                $stm = $this->getDb()->prepare('DELETE FROM ca_assessment_placement_type WHERE assessment_id = ? AND placement_type_id = ?');
                $stm->bindParam(1, $assessmentId);
                $stm->bindParam(2, $placementTypeId);
            }
            $stm->execute();
        } catch (Exception $e) {}
    }

    /**
     * @param int $assessmentId
     * @param int $placementTypeId
     */
    public function addPlacementType($assessmentId, $placementTypeId)
    {
        try {
            if ($this->hasPlacementType($assessmentId, $placementTypeId)) return;
            $stm = $this->getDb()->prepare('INSERT INTO ca_assessment_placement_type (assessment_id, placement_type_id)  VALUES (?, ?)');
            $stm->bindParam(1, $assessmentId);
            $stm->bindParam(2, $placementTypeId);
            $stm->execute();
        } catch (Exception $e) {}
    }

    /**
     * @param int $assessmentId
     * @return array
     */
    public function findPlacementTypes($assessmentId)
    {
        $arr = array();
        try {
            $stm = $this->getDb()->prepare('SELECT placement_type_id FROM ca_assessment_placement_type WHERE assessment_id = ?');
            $stm->bindParam(1, $assessmentId);
            $stm->execute();
            foreach($stm as $row) {
                $arr[] = $row->placement_type_id;
            }
        } catch (Exception $e) {}
        return $arr;
    }



    // Link to subjects

    /**
     * @param int $subjectId
     * @param int $assessmentId
     * @return boolean
     */
    public function hasSubject($subjectId, $assessmentId)
    {
        try {
            $stm = $this->getDb()->prepare('SELECT * FROM ca_assessment_subject WHERE subject_id = ? AND assessment_id = ?');
            $stm->bindParam(1, $subjectId);
            $stm->bindParam(2, $assessmentId);
            $stm->execute();
            return ($stm->rowCount() > 0);
        } catch (Exception $e) {}
        return false;
    }

    /**
     * @param int $subjectId
     * @param int $assessmentId (optional) If null all are to be removed
     */
    public function removeSubject($subjectId, $assessmentId = null)
    {
        try {
            $stm = $this->getDb()->prepare('DELETE FROM ca_assessment_subject WHERE subject_id = ?');
            $stm->bindParam(1, $subjectId);
            if ($assessmentId) {
                $stm = $this->getDb()->prepare('DELETE FROM ca_assessment_subject WHERE subject_id = ? AND assessment_id = ?');
                $stm->bindParam(1, $subjectId);
                $stm->bindParam(2, $assessmentId);
            }
            $stm->execute();
        } catch (Exception $e) {}
    }

    /**
     * @param int $subjectId
     * @param int $assessmentId
     */
    public function addSubject($subjectId, $assessmentId)
    {
        try {
            if ($this->hasSubject($subjectId, $assessmentId)) return;
            $stm = $this->getDb()->prepare('INSERT INTO ca_assessment_subject (subject_id, assessment_id)  VALUES (?, ?)');
            $stm->bindParam(1, $subjectId);
            $stm->bindParam(2, $assessmentId);
            $stm->execute();
        } catch (Exception $e) {}
    }
}