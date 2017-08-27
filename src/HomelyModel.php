<?php
namespace HomelyDb;

use HomelyDb\Exceptions\HomelyException;

class HomelyModel
{
    private $db;

    protected $tableName;

    protected $primaryKey = 'id';

    private $hasMany = [];

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
        $class = (substr_replace($class, '', 0, strrpos($class, '\\') + 1));
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
        if (isset($data[$this->primaryKey])) {
            if (property_exists($this, 'updatedAt')) {
                $this->updatedAt = date('Y-m-d H:i:s');
            }

            $data = $this->toSave();

            return $this->getDb()->update($this->tableName, $data, [$this->primaryKey => $data[$this->primaryKey]]);
        }

        if (property_exists($this, 'createdAt')) {
            $this->createdAt = date('Y-m-d H:i:s');
        }

        $data = $this->toSave();

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

        $data = $queryBuilder
            ->select($criteria)
            ->from($this->tableName)
            ->execute()
            ->fetchAll();

        return $this->allToModel($data);
    }

    public function getAllWithRelations($returnArray = false)
    {
        $data = $this->getQueryBuilder()->select($this->prepareFields())->from($this->tableName);
        $tableNames = [];

        foreach ($this->hasMany as &$class) {
            $data->addSelect($class['class']->prepareFields());
            $data->leftJoin(
                $this->tableName,
                $class['class']->getTableName(),
                $class['class']->getTableName(),
                $this->tableName.'.'.$class['primaryId'].' = '.$class['class']->getTableName().'.'.$class['referencedId']
            );

            $tableNames[$class['class']->getTableName()] = [
                'primaryKeyMapped' => $class['class']->getTableName().'__'.$class['class']->getPrimaryKey(),
                'primaryKey' => $class['class']->getPrimaryKey(),
                'class' => &$class['class']
            ];
        }

        $data = $data->execute()->fetchAll();

        $primaryKeyIndex = $this->getTableName().'__'.$this->getPrimaryKey();

        $result = [];

        foreach ($data as $row) {
            foreach ($row as $key => $value) {
                $table = explode('__', $key);

                if ($table[0] == $this->getTableName()) {
                    $result[$row[$primaryKeyIndex]][$table[1]] = $value;
                } else {
                    $manyTableField = lcfirst($table[0]);

                    if (($tableNames[$table[0]]['primaryKey'] == $table[1]) && $value == null) {
                        $result[$row[$primaryKeyIndex]][$manyTableField] = [];
                        break;
                    }

                    $result[$row[$primaryKeyIndex]][$manyTableField][$row[$tableNames[$table[0]]['primaryKeyMapped']]][$table[1]] = $value;
                }
            }
        }

        if ($returnArray) {
            foreach ($result as &$row) {
                foreach ($tableNames as $key => $tableName) {
                    $manyTableField = lcfirst($key);
                    $row[$manyTableField] = array_values($row[$manyTableField]);
                }
            }

            return array_values($result);
        }

        foreach ($result as &$row) {
            $row = clone($this)->populateFromArray($row);

            foreach ($tableNames as $key => $tableName) {
                $manyTableField = lcfirst($key);

                $joins = array_map(function ($object) use ($tableName) {
                    $object = clone($tableName['class'])->populateFromArray($object);

                    return $object;
                }, array_values($row->{$manyTableField}));

                $row->{$manyTableField} = $joins;
            }
        }

        return array_values($result);
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

    public function allToModel($data)
    {
        return array_map(function ($row) {
            return $this->populateFromArray($row);
        }, $data);
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
            ->fetch();

        return $this->populateFromArray($data);
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

        /** @var HomelyModel $class */
        $class =  clone $this;

        foreach ($data as $attr => $value) {
            $classAttr = $class->toCamelCase($attr);

            $class->{$classAttr} = $value;
        }

        return $class;
    }

    public function toArray()
    {
        $fields = $this->getReflectionClass($this)->getProperties(\ReflectionProperty::IS_PUBLIC);

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

    protected function setHasMany($className, $primaryId = null, $referencedId = null, $fields = '*')
    {
        if (!isset($this->hasMany[$className])) {
            if (!class_exists($className)) {
                throw new HomelyException("Class {$className} not found");
            }

            /** @var HomelyModel $class */
            $class = new $className;

            if ($primaryId == null) {
                $primaryId = $this->getPrimaryKey();
            }

            if ($referencedId == null) {
                $referencedId = $this->tableName.'_'.$class->getPrimaryKey();
            }

            $this->hasMany[$className] = [
                'class' => $class,
                'primaryId' => $primaryId,
                'referencedId' => $referencedId,
                'fields' => $fields
            ];
        }
    }

    public function hasMany($className, $primaryId = null, $referencedId = null, $fields = '*')
    {
        $this->setHasMany($className, $primaryId, $referencedId, $fields);

        /** @var HomelyModel $class */
        $class = $this->hasMany[$className]['class'];

        $primaryId = $this->hasMany[$className]['primaryId'];
        $referencedId = $this->hasMany[$className]['referencedId'];
        $fields = $this->hasMany[$className]['fields'];

        $data = $class->getQueryBuilder()
            ->select($fields)
            ->from($class->tableName)
            ->where($class->getQueryBuilder()->expr()->eq($referencedId, ':id'))
            ->setParameter('id', (int)$this->{$primaryId})
            ->execute()
            ->fetchAll();

        return $class->allToModel($data);
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

    private function toCamelCase($string)
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
