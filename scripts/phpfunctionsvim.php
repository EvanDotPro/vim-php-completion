#!/usr/bin/php
<?php
/*
 * Download the chunked xhtml version of the php manual and put it in 
 * /tmp/php-chunked-xhtml
 *
 * Then run svn co http://svn.php.net/repository/phpdoc/modules/doc-en /tmp/phpdoc
 *
 * This file loops through all the PHP native functions and generates the string 
 * for the phpcompletion.vim file bundled with Vim, based on the latest PHP 
 * functions.
 */
$functionList = `ls /tmp/php-chunked-xhtml | grep "^function\." | cut -d'.' -f2`;
$functionList = str_replace('-','_',$functionList);
$functionList = explode("\n",$functionList);
array_pop($functionList);
//echo'<pre>';print_r($functionList);die();
$functions = array();
$output = 'let g:php_builtin_functions = {';
foreach($functionList as $i=>$line){
    $lineOutput = '';
    $command = "./functiondetails.php {$line}";
    $functions[$line] = unserialize(trim(`$command`));
    if(count($functions[$line]) == 2 && isset($functions[$line]['deprecated'])) continue;
    if(!$functions[$line]) die("ERROR: {$command}");
    if($i > 0) $lineOutput .= ',';
    $lineOutput .= "\n\ '{$functions[$line]['function']}(': '";
    $lineOutput .= paramSignature($functions[$line]);
    if(isset($functions[$line]['return_type'])){
        $lineOutput .= " | {$functions[$line]['return_type']}";
    }
    $lineOutput .= "'";
    echo ($i+1).'/'.count($functionList).': '.str_replace("\n",'',$lineOutput)."\n";
    $output .= $lineOutput;
}
$output .= "\n\ }";
file_put_contents('/tmp/phpfunctions.vim',$output);

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
