<?php
$command = 'ls /tmp/php-chunked-xhtml | grep "^class\."';
$lines = explode("\n", `$command`);
array_pop($lines);
$classes = array();
echo '<pre>';
foreach($lines as $i=>$line){
    $name = explode('.',$line);
    $name = $name[1];
    if(isset($_GET['class'])){
        if(strtolower($_GET['class']) != strtolower($name)){ continue; }
    }
    $xml = new tidy();
    $xml = $xml->repairfile('/tmp/php-chunked-xhtml/'.$line, array('input-xml'=>true, 'wrap'=>0));
    $xml = simplexml_load_string($xml);
    if(!$xml){
        die("Bad HTML or something: " . $line);
    }
    $class = $xml->xpath('//b[@class="classname"]');
    if(!$class || !isset($class[0])){
        $class = $name;
    } else {
        $class = (string)$class[0];
    }
    $command = 'find /tmp/phpdoc/en -iname "'.$name.'.xml" | xargs grep -l -i "<classname>'.$class.'</classname>" | grep -v ".svn" | cut -d":" -f1 | uniq | grep -v -i "'.$name.'/'.$name.'.xml"';
    $file = trim(`$command`);
    if(!$file){
        $name = substr($name, strlen($name) - floor(strlen($name)/2));
        $command = 'find /tmp/phpdoc/en -iname "*'.$name.'.xml" | xargs grep -l -i "<classname>'.$class.'</classname>" | grep -v ".svn" | cut -d":" -f1 | uniq';
        $file = trim(`$command`);
        if(!$file){
            $command = 'find /tmp/phpdoc/en -iname "'.$class.'.xml" | xargs grep -l -i "<classname>'.$class.'</classname>" | grep -v ".svn" | cut -d":" -f1 | uniq';
            $file = trim(`$command`);
            if(!$file){
                die($command);
            }
        }
    }
    $xml = new tidy();
    $xml = $xml->repairfile($file, array('input-xml'=>true, 'wrap'=>0));
    $xml = simplexml_load_string($xml);
    //print_r($xml);die();
    //
    $xml->registerXPathNamespace("n", "http://docbook.org/ns/docbook");
    $classname = $xml->xpath('//n:classsynopsis/n:ooclass/n:classname');
    if(count($classname) > 0){
        $classes[$name]['class'] = (string)$classname[0]; //$xml->partintro;
    } else {
        $xml->registerXPathNamespace("n", "http://docbook.org/ns/docbook");
        $classname = $xml->xpath('//n:classname');
        $classes[$name]['class'] = (string)$classname[0]; //$xml->partintro;
    }
    //$classes[$name]['file'] = $file;
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
    $xml->registerXPathNamespace("n", "http://docbook.org/ns/docbook");
    $description = $xml->xpath('//n:section/n:para');
    if(count($description) > 0) {
        $description = (string)$description[0]->saveXML();
        $classes[$name]['description'] = trim(strip_tags(preg_replace(array_keys($replace),array_values($replace),$description)));
        // again to get rid of double spaces after strip_tags
        // see Exception class
        $classes[$name]['description'] = trim(strip_tags(preg_replace(array_keys($replace),array_values($replace),$classes[$name]['description'])));
    }



    /*
     * Reflection is more reliable... see ArrayIterator. Manual says extends 
     * Traversable, but it implements it as an interface
     *
     * Crap... Now it's the other way arround. Reflection says PharData extends 
     * RecursiveDirectoryIterator, but the manual says it extends Phar which 
     * then extends RecursiveDirectoryIterator. (The manual is right.)
     */
    $xml->registerXPathNamespace("n", "http://docbook.org/ns/docbook");
    $extends = $xml->xpath('//n:ooclass[n:modifier[1] = "extends"]/n:classname');
    $extendsManual = false;
    if(count($extends) > 0){
        $extendsManual = (string)$extends[0];
    }
    /*
    $xml->registerXPathNamespace("n", "http://docbook.org/ns/docbook");
    $interfaces = $xml->xpath('//n:oointerface/n:interfacename');
    foreach($interfaces as $interface){
        $classes[$name]['interfaces'][] = (string)$interface;
    }
     */
    try {
        $reflection = new ReflectionClass($classes[$name]['class']);
    } catch (ReflectionException $e) {
        $classes[$name]['error'] = $e->getMessage();
        continue;
    }
    if($reflection->isAbstract()) $classes[$name]['abstract'] = 1;
    if($reflection->isFinal()) $classes[$name]['final'] = 1;
    if($reflection->isInterface()) $classes[$name]['interface'] = 1;

    $constants = $reflection->getConstants();
    if(count($constants) > 0) $classes[$name]['constants'] = $constants;

    $interfaces = $reflection->getInterfaceNames();
    if(count($interfaces) > 0) $classes[$name]['implements'] = $interfaces;

    $parentClass = $reflection->getParentClass();
    if($parentClass)  $classes[$name]['extends'] = $parentClass->getName();

    if(isset($classes[$name]['extends']) && 
        $extendsManual != false &&
        $extendsManual != $classes[$name]['extends'] &&
        (is_array($classes[$name]['implements']) && !in_array($extendsManual, $classes[$name]['implements']) || !is_array($classes[$name]['implements']))){
            $classes[$name]['extends'] = $extendsManual;
    }


    $properties = $reflection->getProperties();
    foreach($properties as $property){
        if($property->getDeclaringClass()->getName() != $reflection->getName()) continue;
        $details = array();
        $details['name'] = $property->getName();

        if($property->isPublic()) $details['visibility'] = 'public';
        if($property->isProtected()) $details['visibility'] = 'protected';
        if($property->isPrivate()) $details['visibility'] = 'private';
        if($property->isStatic()) $details['static'] = 1;
        $classes[$name]['properties'][] = $details;


    }

    $methods = $reflection->getMethods();
    foreach($methods as $method){
        // $method->getDeclaringClass() breaks on PharData::addFile (it's 
        // declared by the parent class Phar, but reflection thinks it's 
        // PharData's)
        if($method->getDeclaringClass()->getName() != $reflection->getName()) continue;
        // see PDO::__sleep and PDO::__wakeup
        //if($method->getName() == '__sleep' || $method->getName() == '__wakeup') continue;
        $command = "./functiondetails.php {$classes[$name]['class']}::{$method->getName()}";
        $details = @unserialize(trim(`$command`));

        // attempted fix for bug mentioned about about getDeclaringClass()
        // if it appears to be undocumented, but the method exists for the 
        // parent...
        if(!$details && isset($classes[$name]['extends'])){
            $command = "./functiondetails.php {$classes[$name]['extends']}::{$method->getName()}";
            $details = @unserialize(trim(`$command`));
            if($details){ 
                // bug detected
                continue;
            }
        }

        //$details['declaredby'] = $method->getDeclaringClass()->getName();
        if(!$details){
            // see Phar::getAlias
            $details = array('function' => "{$classes[$name]['class']}::{$method->getName()}");
            // no docs, try reflection
            // (version info might still be available via docs)
            $params = $method->getParameters();
            foreach($params as $param){
                $parami = array('name' => $param->getName());

                $details['params'][] = $parami;
            }
            $details['undocumented'] = 1;
            $details['declaredby'] = $method->getDeclaringClass()->getName();
        }
        if($details){
            if($method->isAbstract()) $details['abstract'] = 1;
            if($method->isPublic()) $details['visibility'] = 'public';
            if($method->isProtected()) $details['visibility'] = 'protected';
            if($method->isPrivate()) $details['visibility'] = 'private';
            if($method->isFinal()) $details['final'] = 1;
            if($method->isStatic()) $details['static'] = 1;
            if($method->isDeprecated()) $details['deprecated'] = 1;
            
            $classes[$name]['methods'][] = $details;
        } else {
            $classes[$name]['methods'][] = "ERROR: {$classes[$name]['class']}::{$method->getName()}";
        }
    }


    if($i >= (isset($_GET['max']) ? $_GET['max'] : 10)){
        break;
    }
}
print_r($classes);
