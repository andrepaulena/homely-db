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

        if(!$this->tableName){
            $class = get_called_class();
            $class = (substr_replace($class,'', 0, strrpos($class,'\\') + 1));

            $this->tableName = str_replace('Model','', $class);
        }
    }

    public function getQueryBuilder()
    {
        return $this->db->createQueryBuilder();
    }

    public function save()
    {
        $data = array_filter($this->toArray());

        if(isset($data[$this->primaryKey])){
            return $this->db->update($this->tableName, $data,[$this->primaryKey => $data[$this->primaryKey]]);
        }

        return $this->db->insert($this->tableName, $data);
    }

    public function delete($value)
    {

    }

    public function getAll()
    {

    }

    public function getById($id)
    {

    }

    public function toArray()
    {
        $class = new \ReflectionClass($this);
        $fields = $class->getProperties(\ReflectionProperty::IS_PUBLIC);

        $return = [];

        foreach ($fields as $field){
            $return[$field->name] = $this->{$field->name};
        }

        return $return;
    }
}
