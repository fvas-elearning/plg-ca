<?php
namespace Ca\Db;

use Bs\Db\Traits\TimestampTrait;
use Uni\Db\Traits\InstitutionTrait;

/**
 * @author Mick Mifsud
 * @created 2019-10-31
 * @link http://tropotek.com.au/
 * @license Copyright 2019 Tropotek
 */
class Scale extends \Tk\Db\Map\Model implements \Tk\ValidInterface
{
    use InstitutionTrait;
    use TimestampTrait;

    const TYPE_TEXT = 'text';
    const TYPE_VALUE = 'value';
    const TYPE_CHOICE = 'choice';

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
    public $institutionId = 0;

    /**
     * @var string
     */
    public $name = '';

    /**
     * @var string
     */
    public $description = '';

    /**
     * @var string
     */
    public $type = 'text';

    /**
     * @var bool
     */
    public $multiple = false;

    /**
     * @var string
     */
    public $calcType = 'avg';

    /**
     * @var float
     */
    public $maxValue = 0;

    /**
     * @var \DateTime
     */
    public $modified = null;

    /**
     * @var \DateTime
     */
    public $created = null;


    /**
     * Scale
     */
    public function __construct()
    {
        $this->institutionId = $this->getConfig()->getInstitutionId();
        $this->_TimestampTrait();

    }
    
    /**
     * @param string $uid
     * @return Scale
     */
    public function setUid($uid) : Scale
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
     * @return Scale
     */
    public function setName($name) : Scale
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
     * @param string $description
     * @return Scale
     */
    public function setDescription($description) : Scale
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
     * @param string $type
     * @return Scale
     */
    public function setType($type) : Scale
    {
        $this->type = $type;
        return $this;
    }

    /**
     * return string
     */
    public function getType() : string
    {
        return $this->type;
    }

    /**
     * @param bool $multiple
     * @return Scale
     */
    public function setMultiple($multiple) : Scale
    {
        $this->multiple = $multiple;
        return $this;
    }

    /**
     * return bool
     */
    public function isMultiple() : bool
    {
        return $this->multiple;
    }

    /**
     * @param string $calcType
     * @return Scale
     */
    public function setCalcType($calcType) : Scale
    {
        $this->calcType = $calcType;
        return $this;
    }

    /**
     * return string
     */
    public function getCalcType() : string
    {
        return $this->calcType;
    }

    /**
     * @param float $maxValue
     * @return Scale
     */
    public function setMaxValue($maxValue) : Scale
    {
        $this->maxValue = $maxValue;
        return $this;
    }

    /**
     * return float
     */
    public function getMaxValue() : float
    {
        return $this->maxValue;
    }

    /**
     * @return Option[]|\Tk\Db\Map\ArrayObject
     * @throws \Exception
     */
    public function getOptions()
    {
        return OptionMap::create()->findFiltered(['scaleId' => $this->getVolatileId()]);
    }

    /**
     * @return array
     */
    public function validate()
    {
        $errors = array();
        $errors = $this->validateInstitutionId($errors);

        if (!$this->getName()) {
            $errors['name'] = 'Invalid value: name';
        }

        if (!$this->getType()) {
            $errors['type'] = 'Invalid value: type';
        }

        if ($this->getType() == self::TYPE_CHOICE && $this->isMultiple() && !$this->getCalcType()) {
            $errors['calcType'] = 'Invalid value: calcType';
        }

        if ($this->getType() == self::TYPE_VALUE && !$this->getMaxValue()) {
            $errors['maxValue'] = 'Invalid value: maxValue';
        }

        return $errors;
    }

}
