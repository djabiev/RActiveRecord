<?php
/**
 * Description of RelatedModel
 * 
 * @author liveboard.oksystems.tv
 */
class RActiveRecord extends CActiveRecord {
    private $_relatedRecords = array();
    
    public function init() {
        parent::init();
        foreach ($this->relations() as $relName => $relValue) {
            if ($relValue[0] == self::HAS_MANY)
                $this->_relatedRecords[$relName] = null;
//            elseif ($relValue[0] == self::HAS_ONE or $relValue[0] == self::BELONGS_TO)
//                $this->_relatedRecords[$relName] = null;
        }
    }
    
    public function __get($name) {
        if(key_exists($name, $this->_relatedRecords) and is_array($this->_relatedRecords[$name]))
            return $this->_relatedRecords[$name];
        else
            return parent::__get($name);
    }
    
    public function __set($name, $value) {
        $relations = $this->relations();
        
        if(key_exists($name, $this->_relatedRecords) and $relations[$name][0] == self::HAS_MANY) {
            $relModelName = $relations[$name][1];
            if(is_array($value)) {
                $this->_relatedRecords[$name] = array();
                foreach ($value as $k=>$v) {
                    if(!is_array($v))
                        throw new CException("Two dimensional array is required for HAS_MANY relation");
                    $m = new $relModelName;
                    if(!empty($v[$m->tableSchema->primaryKey])) {
                        $mtemp = $m->findByPk($v[$m->tableSchema->primaryKey]);
                        if(!empty($mtemp))
                            $m = $mtemp;
                    }
                    $m->attributes = $v;
                    $this->_relatedRecords[$name][] = $m;;
                }
            }
        }
        else
            parent::__set($name, $value);
    }
    
    public function beforeValidate() {
        $isValid = parent::beforeValidate();
        
        foreach ($this->relations() as $relName=>$relValue) {
            if ($relValue[0] == self::HAS_MANY and is_array($this->_relatedRecords[$relName])) {
                foreach($this->_relatedRecords[$relName] as $m) {
                    if(!$m->validate()) {
                        $isValid = false;
                        $this->addErrors($m->getErrors());
                    }
                }
            }
        }
        return true;
    }
    
    public function afterSave() {
        parent::afterSave();
        
        foreach ($this->relations() as $relName=>$relValue) {
            if ($relValue[0] == self::HAS_MANY and is_array($this->_relatedRecords[$relName])) {
                $ids = array();
                foreach($this->_relatedRecords[$relName] as $m) {
                    $m->{$relValue[2]} = $this->getPrimaryKey();
                    $m->save();
                    $ids[] = $m->getPrimaryKey();
                }
                $delete = new $relValue[1];
                $cr = new CDbCriteria();
                $cr->addNotInCondition($delete->tableSchema->primaryKey, $ids);
                $cr->addColumnCondition(array($relValue[2]=>$this->getPrimaryKey()));
                foreach ($delete->findAll($cr) as $d)
                    $d->delete();
            }
        }
    }
}

?>
