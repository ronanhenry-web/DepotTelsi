<?php

namespace Taas\Dao\Repository\QualificationsManager;

use Taas\Dao\Repository\RepositoryTaasObject;
use Taas\Dao\Translator\TaasDaoTranslator;
use Psr\Log\LoggerInterface;

class RepositoryCategory extends RepositoryTaasObject {
    public $table = 'parms_category';
    public $schemaTable = 'qualificationsmanager';
    public $primaryKey = 'id';
    public $parentKey = '';
    public $model = 'QualificationsManager';
    public $orderColumn = 'id';
    public $autoId = true;
    public $linkTables = [
                        array('repo'=>'Taas\\Dao\\Repository\\Account\\RepositoryClient',
                            'foreignkey'=>'client_id',)
                    ];
    public $columns;
    
    public function __construct(Array $connexions, LoggerInterface $logger, TaasDaoTranslator $translator,$user,$tempDirectory = null,$cacheDirectory = null) {
        parent::__construct($connexions, $logger, $translator,$user,$tempDirectory,$cacheDirectory);
        $this->clearCache = true;
        $this->tableLabel = $this->translator->trans("Category");
        $this->columns= [
            ['name' => 'name',
                'title' => $this->translator->trans("Nom de la catégorie"),
                'edittype' => 'text',
                'search' => true,
                'show' => array("list" => true, "add" => true, "edit" => true, "view" => true),
                'editoptions' => array("maxlength" => 255, "size" => 40),
                'editable' => true,
                'editrules' => array("required" => true),
                'width' => '50'],   
            ['name' => 'parent_id',
                'title' => $this->translator->trans("Catégorie Parente"),
                'edittype' => 'text',
                'formatter' => 'number',
                'formatoptions' => array("thousandsSeparator" => "", "decimalSeparator" => ".", "decimalPlaces" => 0),
                'search' => true,
                'show' => array("list" => true, "add" => true, "edit" => true, "view" => true),
                'editoptions' => array("maxlength" => 19, "size" => 19, "defaultValue" => 50),
                'editable' => true,
                'editrules' => array("minValue" => 0, "maxValue" => 730, "number" => true),
                'width' => '20'],
            ['name' => 'description',
                'title' => $this->translator->trans("Description"),
                'edittype' => 'text',
                'formatter' => 'text',
                'formatoptions' => array("thousandsSeparator" => "", "decimalSeparator" => ".", "decimalPlaces" => 0),
                'search' => true,
                'show' => array("list" => true, "add" => true, "edit" => true, "view" => true),
                'editoptions' => array("maxlength" => 19, "size" => 19, "defaultValue" => 50),
                'editable' => true,
                'editrules' => array("minValue" => 0, "maxValue" => 730, "number" => true),
                'width' => '20'],
            ['name' => 'crc_category_id',
                'title' => $this->translator->trans("Catégorie Kiamo"),
                'edittype' => 'text',
                'formatter' => 'number',
                'formatoptions' => array("thousandsSeparator" => "", "decimalSeparator" => ".", "decimalPlaces" => 0),
                'search' => true,
                'show' => array("list" => true, "add" => true, "edit" => true, "view" => true),
                'editoptions' => array("maxlength" => 19, "size" => 19, "defaultValue" => 50),
                'editable' => true,
                'editrules' => array("minValue" => 0, "maxValue" => 730, "number" => true),
                'width' => '20'], 
        ];
    }
}