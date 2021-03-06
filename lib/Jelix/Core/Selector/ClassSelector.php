<?php
/**
* see Jelix/Core/Selector/SelectorInterface.php for documentation about selectors. 
* @author      Laurent Jouanneau
* @copyright   2005-2014 Laurent Jouanneau
* @link        http://www.jelix.org
* @licence    GNU Lesser General Public Licence see LICENCE file or http://www.gnu.org/licenses/lgpl.html
*/

namespace Jelix\Core\Selector;
use Jelix\Core\App as App;

/**
 * selector for business class
 *
 * business class is a class stored in classname.class.php file in the classes/ module directory
 * or one of its subdirectory.
 * syntax : "module~classname" or "module~classname.
 */
class ClassSelector extends ModuleSelector {
    protected $type = 'class';
    protected $_dirname = 'classes/';
    protected $_suffix = '.class.php';

    /**
    * subpath part in the resource content
    * @since 1.0b2
    */
    public $subpath ='';
    /**
    * the class name specified in the selector
    * @since 1.0b2
    */
    public $className = '';

    function __construct($sel){
        if (jelix_scan_class_sel($sel, $this)) {
            if ($this->module =='') {
                $this->module = App::getCurrentModule ();
            }
            $this->_createPath();
            $this->_createCachePath();
        }
        else {
            throw new Exception('jelix~errors.selector.invalid.syntax', array($sel,$this->type));
        }
    }

    protected function _createPath(){
        if (!isset(App::config()->_modulesPathList[$this->module])) {
            throw new Exception('jelix~errors.selector.module.unknown', $this->toString());
        }
        $this->_path = App::config()->_modulesPathList[$this->module].$this->_dirname.$this->subpath.$this->className.$this->_suffix;

        if (!file_exists($this->_path) || strpos($this->subpath,'..') !== false ) { // second test for security issues
            throw new Exception('jelix~errors.selector.invalid.target', array($this->toString(), $this->type));
        }
    }

    protected function _createCachePath(){
        $this->_cachePath = '';
    }

    public function toString($full=false){
        if($full)
            return $this->type.':'.$this->module.'~'.$this->subpath.$this->className;
        else
            return $this->module.'~'.$this->subpath.$this->className;
    }
}