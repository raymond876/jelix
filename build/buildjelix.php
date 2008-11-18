<?php
/**
* @package     jelix
* @author      Jouanneau Laurent
* @contributor Kévin Lepeltier
* @copyright   2006-2008 Jouanneau laurent
* @copyright   2008 Kévin Lepeltier
* @link        http://www.jelix.org
* @licence     GNU General Public Licence see LICENCE file or http://www.gnu.org/licenses/gpl.html
*/

$BUILD_OPTIONS = array(
'MAIN_TARGET_PATH'=> array(
    "main directory where sources will be copied",  // signification (false = option cachée)
    '_dist',                                        // valeur par défaut (boolean = option booleene)
    '',                                             // regexp pour la valeur ou vide=tout (seulement pour option non booleene)
    ),
'PHP_VERSION_TARGET'=> array(
    "PHP5 version for which jelix will be generated (by default, the target is php 5.1)",
    '5.1'
    ),
'EDITION_NAME'=> array(
    "The edition name of the version (optional)",
    '',
    ),
'ENABLE_PHP_FILTER'=>array(
    "true if jelix can use php filter api (api included in PHP>=5.2)",
    false,
    ),
'ENABLE_PHP_JSON'=>array(
    "true if jelix can use php json api (api included in PHP>=5.2)",
    false,
    ),
'ENABLE_PHP_XMLRPC'=>array(
    "true if jelix can use php xmlrpc api",
    false,
    ),
'ENABLE_PHP_JELIX'=>array(
    "true if jelix can use jelix php extension. WARNING ! EXPERIMENTAL !",
    false,
    ),
'WITH_BYTECODE_CACHE'=> array(
    "says which bytecode cache engine will be recognized by jelix. Possible values :  'auto' (automatic detection), 'apc', 'eaccelerator', 'xcache' or '' for  none",
    'auto',
    '/^(auto|apc|eaccelerator|xcache)?$/',
    ),
'ENABLE_DEVELOPER'=>array(
    "include all developers tools in the distribution (simpletest &cie)",
    true,
    ),
'ENABLE_OPTIMIZED_SOURCE'=>array(
    "true if you want on optimized version of source code, for production server",
    false,
    ),
'STRIP_COMMENT'=>array(
    "true if you want sources with PHP comments deleted (valid only if ENABLE_OPTIMIZED_SOURCE is true)",
    false,
    ),
'PACKAGE_TAR_GZ'=>array(
    "create a tar.gz package",
    false,
    ),
'PACKAGE_ZIP'=>array(
    "create a zip package",
    false,
    ),
'ENABLE_OLD_CLASS_NAMING'=>array(
    "old module class naming (jelix <= 1.0a5) can be used. deprecated for Jelix 1.0 and higher.",
    false,
    ),
'ENABLE_OLD_ACTION_SELECTOR'=>array(
    "old action selector can be used. deprecated for Jelix 1.1 and higher.",
    false,
    ),
'INCLUDE_ALL_FONTS'=>array(
    "True if you want to include lib/fonts content for tcpdf or other",
    false,
    ),
'PROPERTIES_CHARSET_TARGET'=> array(
    "List of charset used for command cch (convert charset)",
    'UTF-8,ISO-8859-1,ISO-8859-15',
    '',
    ),
'DEFAULT_CHARSET'=> array(
    "The default charset of file. useful when convertir some files (cch command)",
    'UTF-8',
    '',
    ),
'PHP50'=> array(
    false,   // hidden option
    false,
    ),
'PHP51'=> array(
    false,
    false,
    ),
'PHP52'=> array(
    false,
    false,
    ),
'SVN_REVISION'=> array(
    false,
    ),
'LIB_VERSION'=> array(
    false,
    '',
    ),
'IS_NIGHTLY'=> array(
    false,
    false,
    ),
'BUILD_FLAGS'=> array(
    false,
    '',
    ),
'EDITION_NAME_x'=> array(
    false,
    '',
    ),
/*''=> array(
    "",
    '',
    '',
    ),*/
);


include(dirname(__FILE__).'/lib/jBuild.inc.php');

//----------------- Preparation des variables d'environnement

Env::setFromFile('LIB_VERSION','lib/jelix/VERSION', true);
$SVN_REVISION = Subversion::revision();

$IS_NIGHTLY = (strpos($LIB_VERSION,'SVN') !== false);

if($IS_NIGHTLY){
    $PACKAGE_NAME='jelix-'.str_replace('SVN', '', $LIB_VERSION);
    if(substr($PACKAGE_NAME,-1,1) == '.')
      $PACKAGE_NAME = substr($PACKAGE_NAME,0,-1);
    $LIB_VERSION = str_replace('SVN', $SVN_REVISION, $LIB_VERSION);
}
else {
    $PACKAGE_NAME='jelix-'.$LIB_VERSION;
}

if($PHP_VERSION_TARGET){
    if(version_compare($PHP_VERSION_TARGET, '5.2') > -1){
        // filter et json sont en standard dans >=5.2 : on le force
        $ENABLE_PHP_FILTER = 1;
        $ENABLE_PHP_JSON = 1;
        $PHP52 = 1;
    }elseif(version_compare($PHP_VERSION_TARGET, '5.1') > -1){
        $PHP51=1;
    }else{
        $PHP50=1;
    }
}else{
    // pas de target définie : donc php 5.0
    $PHP50=1;
}

$BUILD_FLAGS = 0;
if($ENABLE_PHP_JELIX)  $BUILD_FLAGS |=1;
if($ENABLE_PHP_JSON)  $BUILD_FLAGS |=2;
if($ENABLE_PHP_XMLRPC)  $BUILD_FLAGS |=4;
if($ENABLE_PHP_FILTER)  $BUILD_FLAGS |=8;
switch($WITH_BYTECODE_CACHE){
    case 'auto': $BUILD_FLAGS |=112; break;
    case 'apc': $BUILD_FLAGS |=16; break;
    case 'eaccelerator': $BUILD_FLAGS |=32; break;
    case 'xcache': $BUILD_FLAGS |=64; break;
}
if($ENABLE_OLD_CLASS_NAMING)  $BUILD_FLAGS |=256;
if($ENABLE_OLD_ACTION_SELECTOR) $BUILD_FLAGS |= 512;


if($EDITION_NAME ==''){
    $EDITION_NAME_x='userbuild';
    $EDITION_NAME_x.='-f'.$BUILD_FLAGS;
    if($PHP_VERSION_TARGET){
        $EDITION_NAME_x.='-p'.$PHP_VERSION_TARGET;
    }
}else{
    $EDITION_NAME_x = $EDITION_NAME;
}



if( ! $ENABLE_OPTIMIZED_SOURCE)
    $STRIP_COMMENT='';

if($PACKAGE_TAR_GZ || $PACKAGE_ZIP ){

    if($EDITION_NAME_x != '')
        $PACKAGE_NAME.='-'.$EDITION_NAME_x;

    $BUILD_TARGET_PATH = jBuildUtils::normalizeDir($MAIN_TARGET_PATH).$PACKAGE_NAME.'/';
}else{
    $BUILD_TARGET_PATH = jBuildUtils::normalizeDir($MAIN_TARGET_PATH);
}

//----------------- Génération des sources

//... creation des repertoires
jBuildUtils::createDir($BUILD_TARGET_PATH);

//... execution des manifests
jManifest::process('build/manifests/jelix-lib.mn', '.', $BUILD_TARGET_PATH, ENV::getAll(), $STRIP_COMMENT);
jManifest::process('build/manifests/jelix-www.mn', '.', $BUILD_TARGET_PATH, ENV::getAll(), $STRIP_COMMENT);

if( ! $ENABLE_OPTIMIZED_SOURCE){
    jManifest::process('build/manifests/jelix-no-opt.mn', '.', $BUILD_TARGET_PATH , ENV::getAll(), $STRIP_COMMENT);
}
if( ! $ENABLE_PHP_JELIX && ! $ENABLE_OPTIMIZED_SOURCE){
    jManifest::process('build/manifests/jelix-no-ext.mn', '.', $BUILD_TARGET_PATH , ENV::getAll(), $STRIP_COMMENT);
}

if($ENABLE_DEVELOPER){
    jManifest::process('build/manifests/jelix-dev.mn', '.', $BUILD_TARGET_PATH , ENV::getAll());
}
if(!$ENABLE_PHP_JSON){
    jManifest::process('build/manifests/lib-json.mn', '.', $BUILD_TARGET_PATH , ENV::getAll());
}
jManifest::process('build/manifests/jelix-others.mn','.', $BUILD_TARGET_PATH , ENV::getAll());
jManifest::process('build/manifests/jelix-modules.mn', '.', $BUILD_TARGET_PATH, ENV::getAll());
jManifest::process('build/manifests/jelix-admin-modules.mn', '.', $BUILD_TARGET_PATH, ENV::getAll());

if($INCLUDE_ALL_FONTS){
    jManifest::process('build/manifests/fonts.mn', '.', $BUILD_TARGET_PATH , ENV::getAll());
}

if($ENABLE_PHP_JELIX && ($PACKAGE_TAR_GZ || $PACKAGE_ZIP)){
   jManifest::process('build/manifests/jelix-ext-php.mn', '.', $BUILD_TARGET_PATH , ENV::getAll());
}

$var = ENV::getAll();
$var['STANDALONE_CHECKER'] = true;
jManifest::process('build/manifests/jelix-checker.mn','.', $BUILD_TARGET_PATH , $var);

file_put_contents($BUILD_TARGET_PATH.'lib/jelix/VERSION', $LIB_VERSION);

// creation du fichier d'infos sur le build
$view = array('EDITION_NAME', 'PHP_VERSION_TARGET', 'SVN_REVISION', 'ENABLE_PHP_FILTER',
    'ENABLE_PHP_JSON', 'ENABLE_PHP_XMLRPC','ENABLE_PHP_JELIX', 'WITH_BYTECODE_CACHE', 'ENABLE_DEVELOPER',
    'ENABLE_OPTIMIZED_SOURCE', 'STRIP_COMMENT', 'ENABLE_OLD_CLASS_NAMING', 'ENABLE_OLD_ACTION_SELECTOR' );

$infos = '; --- build date:  '.date('Y-m-d H:i')."\n; --- lib version: $LIB_VERSION\n".ENV::getIniContent($view);

file_put_contents($BUILD_TARGET_PATH.'lib/jelix/BUILD', $infos);

//... packages

if($PACKAGE_TAR_GZ){
    exec('tar czf '.$MAIN_TARGET_PATH.'/'.$PACKAGE_NAME.'.tar.gz -C '.$MAIN_TARGET_PATH.' '.$PACKAGE_NAME);
}

if($PACKAGE_ZIP){
    chdir($MAIN_TARGET_PATH);
    exec('zip -r '.$PACKAGE_NAME.'.zip '.$PACKAGE_NAME);
    chdir(dirname(__FILE__));
}

exit(0);
?>