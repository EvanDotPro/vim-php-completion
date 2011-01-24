<?php
$command = 'find /tmp/phpdoc/en -name "constants.xml" | grep "/reference/"';
$files = explode("\n",`$command`);
array_pop($files);
echo '<pre>';
$allConstants = array();
foreach($files as $i=>$file){
    $xml = new tidy();
    $xml = $xml->repairfile($file, array('input-xml'=>true, 'wrap'=>0));
    $xml = simplexml_load_string($xml);
    $xml->registerXPathNamespace("n", "http://docbook.org/ns/docbook");
    $constants = $xml->xpath('//n:*[n:constant and n:type]');
    foreach($constants as $constant){
        $allConstants[(string)$constant->constant] = (string)$constant->type;
    }
    if($i >= (isset($_GET['max']) ? $_GET['max'] : 10)){
        //break;
    }
}
print_r($allConstants);
