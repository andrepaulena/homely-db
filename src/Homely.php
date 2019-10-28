<?php
namespace HomelyDb;

use Doctrine\DBAL\FetchMode;
use HomelyDb\Exceptions\HomelyException;

class Homely
{
    private $db;

    protected $tableName;

    protected $primaryKey = 'id';

    /**
     * @param array $data
     */
    public function __construct($data = [])
    {
        $this->db = HomelyDb::getConnection();

        if (!$this->tableName) {
            $this->tableName = $this->tableNameFactory();
        }


        if (!empty($data)) {
            $this->populateFromArray($data);
        }

        $this->init();

        return $this;
    }

    /** @return \Doctrine\DBAL\Connection */
    public function getDb()
    {
        return $this->db;
    }

    /** @return \Doctrine\DBAL\Query\QueryBuilder */
    public function getQueryBuilder()
    {
        return $this->db->createQueryBuilder();
    }

    protected function init()
    {
    }

    private function tableNameFactory()
    {
        $class = get_called_class();
        $class = str_replace('Model', '', $class);

        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $class));
    }

    public function getTableName()
    {
        return $this->tableName;
    }

    public function getPrimaryKey()
    {
        return $this->primaryKey;
    }

    public function save()
    {
        $data = $this->toSave();

        if (isset($data[$this->primaryKey]) && !empty($data[$this->primaryKey])) {
            if (property_exists($this, 'updatedAt')) {
                $data['updated_at'] = date('Y-m-d H:i:s');
            }

            return $this->getDb()->update($this->tableName, $data, [$this->primaryKey => $data[$this->primaryKey]]);
        }

        unset($data[$this->primaryKey]);

        if (property_exists($this, 'createdAt')) {
            $data['created_at'] = date('Y-m-d H:i:s');
        }

        if ($this->getDb()->insert($this->tableName, $data)) {
            $this->{$this->primaryKey} = $this->getLastId();

            return true;
        }

        return false;
    }

    public function delete($where = [])
    {
        if (empty($where) && $this->{$this->primaryKey} != null) {
            $where = [$this->primaryKey => $this->{$this->primaryKey}];
        } elseif ($this->{$this->primaryKey} == null) {
            throw new HomelyException("Where clause must be defined");
        }

        return $this->db->delete($this->tableName, $where);
    }

    public function getAll($criteria = '*')
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->select($criteria)
            ->from($this->tableName)
            ->execute()
            ->fetchAll(FetchMode::CUSTOM_OBJECT, get_called_class());
    }

    public function prepareFields()
    {
        $fields = array_keys($this->toArray());

        foreach ($fields as &$field) {
            $field = $this->toUnderScoreCase($field);
            $field = $this->getTableName().'.'.$field .' as '.$this->tableName.'__'.$field;
        }

        return implode(', ', $fields);
    }

    public function getAllInArray($criteria = '*')
    {
        $queryBuilder = $this->getQueryBuilder();

        $data = $queryBuilder
            ->select($criteria)
            ->from($this->tableName)
            ->execute()
            ->fetchAll();

        return $data;
    }

    public function getById($id)
    {
        $queryBuilder = $this->getQueryBuilder();

        $data = $queryBuilder
            ->select('*')
            ->from($this->tableName)
            ->where($queryBuilder->expr()->eq($this->primaryKey, ':id'))
            ->setParameter('id', $id)
            ->execute()
            ->fetchAll(FetchMode::CUSTOM_OBJECT, get_called_class());

        if ($data) {
            return $data[0];
        }

        return false;
    }

    public function insert($data)
    {
        return $this->db->insert($this->tableName, $data);
    }

    public function update($data, $criteria = [])
    {
        if (empty($criteria)) {
            $criteria = [$this->primaryKey => $data[$this->primaryKey]];
        }

        return $this->db->update($this->tableName, $data, $criteria);
    }

    public function populateFromArray($data)
    {
        if (!is_array($data)) {
            throw new HomelyException("The parameter must be an array");
        }

        foreach ($data as $attr => $value) {
            $this->{$attr} = $value;
        }

        return $this;
    }

    protected function populateFromDb($data)
    {
        if (!is_array($data)) {
            throw new HomelyException("The parameter must be an array");
        }

        /** @var Homely $class */
        $class =  clone $this;

        foreach ($data as $attr => $value) {
            $classAttr = $class->toCamelCase($attr);

            $class->{$classAttr} = $value;
        }

        return $class;
    }

    public function toArray()
    {
        $fields = $this->getReflectionClass($this)->getProperties();

        $return = [];

        foreach ($fields as $field) {
            $return[$field->name] = $this->{$field->name};
        }

        return $return;
    }

    protected function toSave()
    {
        $fields = $this->getReflectionClass($this)->getProperties(\ReflectionProperty::IS_PUBLIC);

        $return = [];

        foreach ($fields as $field) {
            $key = $this->toUnderScoreCase($field->name);

            $return[$key] = $this->{$field->name};
        }

        return $return;
    }

    public function beginTransaction()
    {
        $this->db->beginTransaction();
    }

    public function commit()
    {
        $this->db->commit();
    }

    public function rollBack()
    {
        $this->db->rollBack();
    }

    /**
     * @param $class
     * @return \ReflectionClass
     */
    private function getReflectionClass($class)
    {
        return new \ReflectionClass($class);
    }

    protected function toCamelCase($string)
    {
        $string = str_replace('_', ' ', $string);
        $string = lcfirst(ucwords($string));
        $string = str_replace(' ', '', $string);

        return $string;
    }

    private function toUnderScoreCase($string)
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $string));
    }

    public function getLastId()
    {
        $queryBuilder = $this->getQueryBuilder();

        $result = $queryBuilder->select($this->primaryKey)
            ->from($this->tableName)
            ->setFirstResult(0)->setMaxResults(1)
            ->orderBy($this->primaryKey, 'DESC')
            ->execute()
            ->fetch();

        return $result[$this->primaryKey];
    }
}
