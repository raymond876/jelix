<?php
/**
* @package     jelix
* @subpackage  forms
* @author      Laurent Jouanneau
* @contributor
* @copyright   2006-2007 Laurent Jouanneau
* @link        http://www.jelix.org
* @licence     http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public Licence, see LICENCE file
*/

/**
 *
*/

define('JFORM_ERRDATA_INVALID',1);
define('JFORM_ERRDATA_REQUIRED',2);

/**
 * base class of all form classes generated by the jform compiler
 * @package     jelix
 * @subpackage  forms
 * @experimental
 */
abstract class jFormsBase {

    /**
     * List of form controls
     * array of jFormsControl objects
     * @var array
     * @see jFormsControl
     */
    protected $_controls = array();

    /**
     * the datas container
     * @var jFormsDataContainer
     */
    protected $_container=null;

    /**
     * says if the form is readonly
     * @var boolean
     */
    protected $_readOnly = false;

    /**
     * content list of available form builder
     * @var boolean
     */
    protected $_builders = array();

    /**
     * @param jFormsDataContainer $container the datas container
     * @param boolean $reset says if the datas should be reset
     */
    public function __construct(&$container, $reset = false){
        $this->_container = & $container;
        if($reset){
            $this->_container->clear();
        }
    }

    /**
     * set form datas from request parameters
     */
    public function initFromRequest(){
        $req = $GLOBALS['gJCoord']->request;
        foreach($this->_controls as $name=>$ctrl){
            $value = $req->getParam($name);
            //if($value !== null) on commente pour le moment,
            //@todo à prevoir un meilleur test, pour les formulaires sur plusieurs pages
            $this->_container->datas[$name]= $value;
        }
    }


    /**
    * add a control to the form
    * @param $control jFormsControl
    */
    protected function addControl($control){
        $this->_controls [$control->ref] = $control;
        if(!isset($this->_container->datas[$control->ref])){
            $this->_container->datas[$control->ref] = $control->value;
        }
    }

    /**
     * check validity of all datas form
     * @return boolean true if all is ok
     */
    public function check(){
        $this->_container->errors = array();
        foreach($this->_controls as $name=>$ctrl){
            $value=$this->_container->datas[$name];
            if($value === null && $ctrl->required){
                $this->_container->errors[$name]=JFORM_ERRDATA_REQUIRED;
            }elseif(!$ctrl->datatype->check($value)){
                $this->_container->errors[$name]=JFORM_ERRDATA_INVALID;
            }
        }
        return count($this->_container->errors) == 0;
    }

    /**
     * set form datas from a DAO
     * @param string $daoSelector the selector of a dao file
     * @see jDao
     */
    public function initFromDao($daoSelector){
        $dao = jDao::create($daoSelector);
        $daorec = $dao->get($this->_container->formId);
        if(!$daorec)
            return new Exception('formId is invalid, couldn\'t get the corresponding dao object');
        foreach($this->_controls as $name=>$ctrl){
            $this->_container->datas[$name] = $daorec->$name;
        }
    }
    /**
     * save datas using a dao.
     * it call insert or update depending the value of the formId stored in the container
     * @param string $daoSelector the selector of a dao file
     * @see jDao
     */
    public function saveToDao($daoSelector){
        $dao = jDao::create($daoSelector);
        $daorec = jDao::createRecord($daoSelector);
        foreach($this->_controls as $name=>$ctrl){
            $daorec->$name = $this->_container->datas[$name];
        }
        if($this->_container->formId){
            $daorec->setPk($this->_container->formId);
            $dao->update($daorec);
        }else{
            $dao->insert($daorec);
        }
    }

    /**
     * set the form  read only or read/write
     * @param boolean $r true if you want read only
     */
    public function setReadOnly($r = true){  $this->_readOnly = $r;  }

    /**
     * return list of errors found during the check
     * @return array
     * @see jFormsBase::check
     */
    public function getErrors(){  return $this->_container->errors;  }

    /**
     * set an error message on a specific field
     * @param string $field the field name
     * @param string $mesg  the error message string 
     */
    public function setErrorOn($field, $mesg){
        $this->_container->errors[$field]=$mesg;
    }

    /**
     *
     * @param string $name the name of the control/data
     * @param string $value the data value
     */
    public function setData($name,$value){
        $this->_container->datas[$name]=$value;
    }
    /**
     *
     * @param string $name the name of the control/data
     * @return string the data value
     */
    public function getData($name){
        if(isset($this->_container->datas[$name]))
            return $this->_container->datas[$name];
        else return null;
    }
    
    /**
     * @return array form datas
     */
    public function getDatas(){ return $this->_container->datas; }
    /**
     * @return jFormsDataContainer
     */
    public function getContainer(){ return $this->_container; }
    
    /**
     * @return array of jFormsControl objects
     */
    public function getControls(){ return $this->_controls; }

    /**
     * @return string the formId
     */
    public function id(){ return $this->_container->formId; }


    /**
     * @param string $buildertype  the type name of a form builder
     * @param string $action action selector where form will be submit
     * @param array $actionParams  parameters for the action
     * @return jFormsBuilderBase
     */
    public function getBuilder($buildertype, $action, $actionParams){
        if(isset($this->_builders[$buildertype])){
            include_once(JELIX_LIB_FORMS_PATH.'jFormsBuilderBase.class.php');
            include_once ($this->_builders[$buildertype][0]);
            $c =  $this->_builders[$buildertype][1];
            return new $c($this, $action, $actionParams);
        }else{
            throw new Exception('invalid form builder type');
        }
    }

}


?>