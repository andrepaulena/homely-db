<?php
namespace HomelyDb;

class HomelyModel
{
    private $db;

    protected $tableName;

    protected $primaryKey = 'id';

    private $hasMany = [];

    /** @return HomelyModel */
    public function __construct()
    {
        $this->db = HomelyDb::getConnection();

        if (!$this->tableName) {
            $this->tableName = $this->tableNameFactory();
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

        return str_replace('Model', '', $class);
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
        $data = array_filter($this->toArray());

        if (isset($data[$this->primaryKey])) {
            return $this->db->update($this->tableName, $data, [$this->primaryKey => $data[$this->primaryKey]]);
        }

        return $this->db->insert($this->tableName, $data);
    }

    public function delete($value)
    {
        return $this->db->delete($this->tableName, [$this->primaryKey => $value]);
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

            $tableNames[$class['class']->getTableName()] = $class['class']->getTableName().'__'.$class['class']->getPrimaryKey();
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

                    $result[$row[$primaryKeyIndex]][$manyTableField][$row[$tableNames[$table[0]]]][$table[1]] = $value;
                }
            }
        }

        foreach ($result as &$row) {
            foreach ($tableNames as $key => $tableName) {
                $manyTableField = lcfirst($table[0]);

                $row[$manyTableField] = array_values($row[$manyTableField]);
            }
        }

        if($returnArray)
        {
            return $result;
        }


        var_dump(array_values($result));
//        var_dump($data);
        exit;




//
//            ->execute()
//            ->fetchAll();

        var_dump($this->hasMany);
        var_dump($data);
        exit;
    }

    public function prepareFields()
    {
        $fields = array_keys($this->toArray());

        foreach ($fields as &$field) {
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
            throw new \Exception("The parameter must be an array");
        }

        foreach ($data as $attr => $value) {
            $this->{$attr} = $value;
        }

        return $this;
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

    protected function setHasMany($className, $primaryId = null, $referencedId = null, $fields = '*')
    {
        if (!isset($this->hasMany[$className])) {
            if (!class_exists($className)) {
                throw new \Exception("Class {$className} not found");
            }

            /** @var HomelyModel $class */
            $class = new $className;

            if ($primaryId == null) {
                $primaryId = $this->getPrimaryKey();
            }

            if ($referencedId == null) {
                $referencedId = $this->tableName.ucfirst($class->getPrimaryKey());
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

    /**
     * @param $class
     * @return \ReflectionClass
     */
    private function getReflectionClass($class)
    {
        return new \ReflectionClass($class);
    }
}
