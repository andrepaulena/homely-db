<?php
namespace HomelyDb;

class HomelyCli
{
    protected $db;

    protected $overwriteModel = false;

    protected $modelDir = 'Models';

    protected $namespace = '';

    public function __construct($config)
    {
        HomelyDb::init($config);
        $this->db = HomelyDb::getConnection();
    }

    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
    }

    public function setModelDir($dir){
        $this->modelDir = $dir;
    }

    public function overwriteModel($overwriteModel = true){
        $this->overwriteModel = $overwriteModel;
    }

    public function run(){
        $sm = $this->db->getSchemaManager();
        $tables = $sm->listTables();

        $template = file_get_contents('src/TemplateModel.php');

        if(!is_dir($this->modelDir)){
            mkdir($this->modelDir,0775,true);
        }

        /** @var \Doctrine\DBAL\Schema\Table $table */
        foreach ($tables as $table){
            $fileName = $this->modelDir.'/'.$table->getName().'Model.php';

            if(!is_file($fileName) || $this->overwriteModel){
                $content = "";

                /** @var \Doctrine\DBAL\Schema\Column $column */
                foreach ($table->getColumns() as $column){
                    $content .= '    public $'.$column->getName().";\n\n";
                }

                $content = str_replace('//fields',$content,$template);
                $content = str_replace('Template',ucfirst($table->getName()), $content);

                if($this->namespace){
                    $content = str_replace('templateNamespace', $this->namespace, $content);
                }else{
                    $content = str_replace("namespace templateNamespace;\n", '', $content);
                }

                file_put_contents($fileName,$content);
            }
        }
    }
}
