<?php
/**
* @package    jelix
* @subpackage forms
* @author     Laurent Jouanneau
* @contributor
* @copyright   2006-2007 Laurent Jouanneau
* @link        http://www.jelix.org
* @licence    GNU Lesser General Public Licence see LICENCE file or http://www.gnu.org/licenses/lgpl.html
*/

/**
 *
 */
require_once(JELIX_LIB_FORMS_PATH.'jFormsControl.class.php');

/**
 * generates form class from an xml file describing the form
 * @package     jelix
 * @subpackage  forms
 * @experimental
 */
class jFormsCompiler implements jISimpleCompiler {

    protected $sourceFile;


   public function compile($selector){
      global $gJCoord;
      $sel = clone $selector;

      $this->sourceFile = $selector->getPath();
      $cachefile = $selector->getCompiledFilePath();
      $cacheHtmlBuilderFile = $selector->getCompiledBuilderFilePath ('html');

      // compilation du fichier xml
      $xml = simplexml_load_file ( $this->sourceFile);
      if(!$xml){
         throw new jException('jelix~formserr.invalid.xml.file',array($this->sourceFile));
      }

      /*if(!isset($xml->model)){
         trigger_error(jLocale::get('jelix~formserr.missing.tag',array('model',$sourceFile)), E_USER_ERROR);
         return false;
      }
      */

      $source=array();
      $source[]='<?php ';
      $source[]='class '.$selector->getClass().' extends jFormsBase {';
      $source[]='    protected $_builders = array( ';
      $source[]='    \'html\'=>array(\''.$cacheHtmlBuilderFile.'\',\''.$selector->getClass().'_builder_html\'), ';
      $source[]='    );';
      $source[]=' public function __construct(&$container, $reset = false){';
      $source[]='          parent::__construct($container, $reset); ';

      $srcHtmlBuilder=array();
      $srcHtmlBuilder[]='<?php class '.$selector->getClass().'_builder_html extends jFormsHtmlBuilderBase {';
      $srcHtmlBuilder[]=' public function __construct($form, $action, $actionParams){';
      $srcHtmlBuilder[]='          parent::__construct($form, $action, $actionParams); ';
      $srcHtmlBuilder[]='  }';

      $srcjs=array();
      $srcjs[]='$js="gForm = new jFormsForm(\'".$this->_name."\');\n";';
      $srcjs[]='$js.="gForm.setDecorator(new ".$errorDecoratorName."());\n";';
      foreach($xml->children() as $controltype=>$control){
            $source[] = $this->generatePHPControl($controltype, $control);
            $srcjs[] =  $this->generateJsControl($controltype, $control);
      }
      $source[]='  }';

      //$source[]=' public function save(){ } ';

      $source[]='} ?>';

      jFile::write($cachefile, implode("\n", $source));
      $srcjs[]='$js.="jForms.declareForm(gForm);\n";';

      $srcHtmlBuilder[]=' public function getJavascriptCheck($errorDecoratorName){';
      $srcHtmlBuilder[]= implode("\n", $srcjs);
      $srcHtmlBuilder[]=' return $js; }';
      $srcHtmlBuilder[]='} ?>';

      jFile::write($cacheHtmlBuilderFile, implode("\n", $srcHtmlBuilder));
      return true;
    }


    protected function generatePHPControl($controltype, $control){

        $source = array();
        $class = 'jFormsControl'.$controltype;

         if(!class_exists($class,false)){
            throw new jException('jelix~formserr.unknow.tag',array($controltype,$this->sourceFile));
         }

         if(!isset($control['ref'])){
            throw new jException('jelix~formserr.attribute.missing',array('ref',$controltype,$this->sourceFile));
         }

         $source[]='$ctrl= new '.$class.'(\''.(string)$control['ref'].'\');';
         if(isset($control['type'])){
            if($controltype != 'input'){
                throw new jException('jelix~formserr.attribute.not.allowed',array('type',$controltype,$this->sourceFile));
            }

            $dt = (string)$control['type'];
            if(!in_array(strtolower($dt), array('string','boolean','decimal','integer','hexadecimal','datetime','date','time','localedatetime','localedate','localetime', 'url','email','ipv4','ipv6'))){
               throw new jException('jelix~formserr.datatype.unknow',array($dt,$controltype,$this->sourceFile));
            }
            $source[]='$ctrl->datatype= new jDatatype'.$dt.'();';
         }else if($controltype == 'checkbox') {
            $source[]='$ctrl->datatype= new jDatatypeBoolean();';
         }else{
            $source[]='$ctrl->datatype= new jDatatypeString();';
         }

         if(isset($control['readonly'])){
            if('true' == (string)$control['readonly'])
                $source[]='$ctrl->readonly=true;';
         }
         if(isset($control['required'])){
            if($controltype == 'checkbox'){
                throw new jException('jelix~formserr.attribute.not.allowed',array('required','checkbox',$this->sourceFile));
            }
            if('true' == (string)$control['required'])
                $source[]='$ctrl->required=true;';
         }

         if(!isset($control->label)){
            throw new jException('jelix~formserr.tag.missing',array('label',$controltype,$this->sourceFile));
         }

         if(isset($control->label['locale'])){
             $label='';
             $labellocale=(string)$control->label['locale'];
             $source[]='$ctrl->label=jLocale::get(\''.$labellocale.'\');';
         }else{
             $label=(string)$control->label;
             $labellocale='';
             $source[]='$ctrl->label=\''.str_replace("'","\\'",$label).'\';';
         }

         switch($controltype){
            case 'checkboxes':
            case 'radiobuttons':
            case 'menulist':
            case 'listbox':
                // recuperer les <items> attr label|labellocale value
                if(isset($control['dao'])){
                    $daoselector = (string)$control['dao'];
                    $daomethod = (string)$control['daomethod'];
                    $daolabel = (string)$control['daolabelproperty'];
                    $daovalue = (string)$control['daovalueproperty'];
                    $source[]='$ctrl->datasource = new jFormDaoDatasource(\''.$daoselector.'\',\''.
                        $daomethod.'\',\''.$daolabel.'\',\''.$daovalue.'\');';

                }else{
                    $source[]='$ctrl->datasource= new jFormStaticDatasource();';
                    $source[]='$ctrl->datasource->datas = array(';

                    foreach($control->item as $item){
                        $value ="'".str_replace("'","\\'",(string)$item['value'])."'=>";
                        if(isset($item['label'])){
                            $source[] = $value."'".str_replace("'","\\'",(string)$item['label'])."',";
                        }elseif(isset($item['labellocale'])){
                            $source[] = $value."jLocale::get('".(string)$item['labellocale']."'),";
                        }else{
                            $source[] = $value."'".str_replace("'","\\'",(string)$item['value'])."',";
                        }
                    }
                    $source[]=");";
                }
               break;
         }

         if(isset($control['multiple'])){
            if($controltype != 'listbox'){
                throw new jException('jelix~formserr.attribute.not.allowed',array('multiple',$controltype,$this->sourceFile));
            }
            if('true' == (string)$control['multiple'])
                $source[]='$ctrl->multiple=true;';
         }

         $source[]='$this->addControl($ctrl);';
         return implode("\n", $source);
    }

    protected function generateJsControl($controltype, $control){
        $source = array();

        if(isset($control['type'])){
            $dt = (string)$control['type'];
        }else{
            if($controltype == 'checkbox')
                $dt = 'boolean';
            else
                $dt = 'string';
        }

        if(isset($control->label['locale'])){
            $source[]='$label = str_replace("\'","\\\'",jLocale::get(\''.(string)$control->label['locale'].'\'));';
        }else{
            $source[]='$label = str_replace("\'","\\\'",\''.str_replace("'","\\'",(string)$control->label).'\');';
        }
        $source[]='$js.="gControl = new jFormsControl(\''.(string)$control['ref'].'\', \'".$label."\', \''.$dt.'\');\n";';

        if(isset($control['readonly']) && 'true' == (string)$control['readonly']){
            $source[]='$js.="gControl.readonly = true;\n";';
        }
        if(isset($control['required']) && 'true' == (string)$control['required']){
            $source[]='$js.="gControl.required = true;\n";';
        }

        $source[]='$js.="gControl.errRequired=\'".str_replace("\'","\\\'",jLocale::get(\'jelix~formserr.js.err.required\',$label))."\';\n";';
        $source[]='$js.="gControl.errInvalid =\'".str_replace("\'","\\\'",jLocale::get(\'jelix~formserr.js.err.invalid\', $label))."\';\n";';
        if(isset($control['multiple']) && 'true' == (string)$control['multiple']){
            $source[]='$js.="gControl.multiple = true;\n";';
        }

         $source[]='$js.="gForm.addControl( gControl);\n";';

         return implode("\n", $source);
    }



        /* on génére en php, du php qui génère du javascript !  oui oui :-D
    
        au final, le javascript généré dans la page html doit ressembler à cela

        gForm = new jFormsForm('name');
        gForm.setDecorator(new jFormsErrorDecoratorAlert());
        
        gControl = new jFormsControl('name', 'a label', 'datatype');
        gControl.required = true;
        gControl.errInvalid='';
        gControl.errRequired='';
        gForm.addControl( gControl);
        ...
        jForms.declareForm(gForm);


        onsubmit="return jForms.verifyForm(this)"


        // le code php généré dans le builder

        $js="gForm = new jFormsForm('".$this->getFormName()."');\n";
        $js.="gForm.setDecorator(new jFormsErrorDecoratorAlert());\n";
        $label = 'a label';
        ou
        $label = jLocale::get('mod~cle_locale_user');
        $js.="gControl = new jFormsControl('name', '".str_replace("'","\\'", $label)."', 'datatype');\n";
        $js.="gControl.required = true;\n";

        $invalid = jLocale::get('jelix~forms.check.invalid',$label));
        ou
        $invalid = jLocale::get('mod~cle_locale_user');
        ou
        $invalid = 'bla bla';
        $js.="gControl.errInvalid='".str_replace("'","\\'",$invalid)."';\n";


        $required = jLocale::get('jelix~forms.check.required',$label));
        ou
        $required = jLocale::get('mod~cle_locale_user');
        ou
        $required = 'bla bla';

        $js.="gControl.errRequired='".str_replace("'","\\'",$required)."';\n";
        $js.="gForm.addControl( gControl);\n";
        ...
        $js.="jForms.declareForm(gForm);\n";

        */
}

?>