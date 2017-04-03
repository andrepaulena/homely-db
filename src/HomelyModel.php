<?php
namespace HomelyDb;

class HomelyModel
{
    private $db;

    protected $tableName;

    protected $primaryKey = 'id';

    public function __construct()
    {
        $this->db = HomelyDb::getConnection();

        if (!$this->tableName) {
            $class = get_called_class();
            $class = (substr_replace($class, '', 0, strrpos($class, '\\') + 1));

            $this->tableName = str_replace('Model', '', $class);
        }
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

        $return = array_map(function ($row) {
            return $this->populateFromArray($row);
        }, $data);

        return $return;
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
        $class = new \ReflectionClass($this);
        $fields = $class->getProperties(\ReflectionProperty::IS_PUBLIC);

        $return = [];

        foreach ($fields as $field) {
            $return[$field->name] = $this->{$field->name};
        }

        return $return;
    }
}
