#!/usr/bin/php
<?php
$line = strtolower($argv[1]);
$function = array();
$tidyOptions = array('input-xml'=>true, 'wrap'=>0,'input-encoding'=>'utf8','output-encoding'=>'utf8');
//$command = "find /tmp/phpdoc/en -name '*.xml' | xargs grep -l -n -s \"<methodname>$line</methodname>\" | xargs grep -L -n -s \"<ooclass>\" | cut -d':' -f1 | grep \"/functions/\"";
if(strpos($line, '::') !== false){
    $fname = explode('::', $line);
    $fnameSearch = trim($fname[1],'_');
    $className = $fname[0];
    $command = "find /tmp/phpdoc/en -iname '{$fnameSearch}.xml' | xargs grep -i -l -n -s \"<methodname>{$line}</methodname>\" | cut -d':' -f1 | grep -i '/{$className}/{$fnameSearch}.xml'";
} else {
    $command = "find /tmp/phpdoc/en -name '*.xml' | xargs grep -i -l -n -s \"<methodname>{$line}</methodname>\" | xargs grep -L -n -s \"<ooclass>\" | xargs grep -L -n -s \"<refpurpose>&Alias; <function>\" | grep \"/functions/\" | cut -d':' -f1";
}
$firstCmd = $command;
$file = trim(`$command`);
if(!$file){ 
    //echo "<b>{$line} - No doc xml found: {$command}</b>\n";
    // check for alias
    $command = "find /tmp/phpdoc/en -name '*.xml' | xargs grep -i -l -n -s \"<refname>{$line}</refname>\" | xargs grep -l -n -s \"<refpurpose>&Alias; \" | cut -d':' -f1 | grep \"/functions/\"";
    $file = trim(`$command`);
    if($file){
        $xml = new tidy();
        $xml = $xml->repairfile($file, $tidyOptions);
        $xml = simplexml_load_string($xml);
        $function['function'] = $line;
        $function['alias_of'] = (string)$xml->refnamediv->refpurpose->function;
        if(!$function['alias_of']) $function['alias_of'] = (string)$xml->refnamediv->refpurpose->methodname; 
        die(serialize($function));
    } else {
        $command = "find /tmp/phpdoc/en -name '*.xml' | xargs pcregrep -i -l -n -s \"<[^>]+>com_get</[^>]+>\" | xargs grep -l deprecated | cut -d':' -f1";
        if(trim(`$command`)){
            die(serialize(array('function' => $line, 'deprecated' => 1)));    
        }
        // With PharData::addFile() this means that the method belongs to the parent
        die("Error with {$line}... no doc xml found\n{$firstCmd}\n");
    }
}
$xml = new tidy();
$xml = $xml->repairfile($file, $tidyOptions);
$xml = simplexml_load_string($xml);
if(!$xml->refsect1[0]){
    var_dump($xml);
    die("Error: bad XML object: {$line} ... {$file}");
}
if(strlen($xml->refsect1[0]->attributes()->role) > 0 && $xml->refsect1[0]->attributes()->role != 'description'){ die("Error with {$line}... refsect1 xml unexpected."); }
$function['function'] = (string)$xml->refsect1[0]->methodsynopsis->methodname;
$function['return_type'] = (string)$xml->refsect1[0]->methodsynopsis->type;
if(!$function['return_type'] && $fname[1] == '__construct'){
    $function['return_type'] = 'void';
}

$parentPath = realpath(dirname($file).'/../');
$versionFile = $parentPath.'/versions.xml';
if(file_exists($versionFile)){
    $xmlVer = new tidy();
    $xmlVer = $xmlVer->repairfile($versionFile, $tidyOptions);
    $xmlVer = simplexml_load_string($xmlVer);
    $ver = $xmlVer->xpath('//function[@name="'.$line.'"]');
    if(count($ver) == 0 && strpos($line, '::__')){
        // See Phardata::__construct
        $line2 = str_replace('__','_',$line);
        $ver = $xmlVer->xpath('//function[@name="'.$line2.'"]');
        if(count($ver) > 0){ 
            // phpdoc typo
        }
    }
    if(count($ver) > 0){
        $function['version'] = (string)$ver[0]->attributes()->from;
    }
}

if($function['return_type'] == 'object'){
    foreach($xml->refsect1 as $refsect){
        if($refsect->attributes()->role != 'returnvalues'){ continue; }
        foreach($refsect->para->type as $type){
            $type = (string)$type;
            if($type != $function['return_type']){
                //$function['return_class'] = $type;
                $function['return_type'] = $type;
                break 2;
            }
        }
    }
}
$replace = array(
    "/\n/"                          => ' ',
    '/(\<\/[^>]+\>)(\w{2,})/'       => '${1} ${2}',
    '/\<para\>/'                    => '',
    '/\<\/para\>/'                  => '',
    '/\<function\>/'                => '{',
    '/\<\/function\>/'              => '}',
    '/\<parameter\>/'               => '[',
    '/\<\/parameter\>/'             => ']',
    '/\<classname\>/'               => '|',
    '/\<\/classname\>/'             => '|',
    '/\s+/'                         => ' '
);
$function['description'] = (string)$xml->refnamediv->refpurpose->saveXML();
$function['description'] = strip_tags(preg_replace(array_keys($replace),array_values($replace),$function['description']));

$function['detail_desc'] = (string)$xml->refsect1[0]->para->saveXML();
$function['detail_desc'] = strip_tags(preg_replace(array_keys($replace),array_values($replace),$function['detail_desc']));

$xml->registerXPathNamespace("n", "http://docbook.org/ns/docbook");
$params = $xml->xpath('//n:refsect1/*/n:methodparam');
//$params = $xml->refsect1[0]->methodsynopsis->methodparam;
foreach($params as $param){
    $thisParam = array();
    $thisParam['name'] = (string)$param->parameter;
    $thisParam['type'] = (string)$param->type;
    if($param->attributes()->choice == 'opt'){
        $thisParam['optional'] = true;
    }
    if($param->parameter->attributes()->role == 'reference'){
        $thisParam['reference'] = true;
    }
    if($param->initializer){
        $thisParam['default_value'] = (string)$param->initializer;
    }
    $function['params'][] = $thisParam;
}
echo serialize($function);
