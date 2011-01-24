<?php
/*
 * Download the chunked xhtml version of the php manual and put it in 
 * /tmp/php-chunked-xhtml
 *
 * Then run svn co http://svn.php.net/repository/phpdoc/modules/doc-en /tmp/phpdoc
 *
 * This is basically just for quickly viewing parsed data from a function in the 
 * browser. /phpfunctions.php?function=substr
 */
$functionList = `ls /tmp/php-chunked-xhtml | grep "^function\." | cut -d'.' -f2`;
$functionList = str_replace('-','_',$functionList);
$functionList = explode("\n",$functionList);
array_pop($functionList);
//echo'<pre>';print_r($functionList);die();
$functions = array();
echo '<pre>';
foreach($functionList as $i=>$line){
    $lineOutput = '';
    if(isset($_GET['function'])){
        if($_GET['function'] != $line){ continue; }
    }
    $command = "./functiondetails.php {$line}";
    $functions[$line] = unserialize(trim(`$command`));
    $functions[$line]['signature'] = "{$functions[$line]['function']}( ".paramSignature($functions[$line])." )";
    if(count($functions[$line]) == 2 && isset($functions[$line]['deprecated'])) continue;
    if(!$functions[$line]) die("ERROR: {$command}");
    print_r($functions[$line]);
    if($i >= (isset($_GET['max']) ? $_GET['max'] : 10)){
        break;
    }
}

function paramSignature($function){
    $sig = '';
    if(isset($function['alias_of'])) return "Alias of {$function['alias_of']}";
    if(!isset($function['params']) || count($function['params']) == 0) return 'void';
    $optClosers = '';
    foreach($function['params'] as $p => $param){
        if(isset($param['optional'])){
            $sig .= ' [';
            $optClosers .= ']';
        }
        if($p > 0){
            $sig .= ', ';
        }
        $sig .= "{$param['type']} \${$param['name']}";
        if(isset($param['default_value'])){
            $sig .= ' = ';
            $sig .= $param['default_value'];
            $sig .= ' ';
        }

    }
    $sig .= $optClosers;
    return trim($sig);
}
