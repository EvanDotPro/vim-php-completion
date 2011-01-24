<?php
/**
 * This is a sample file to illustrate some of the complexities to be overcome.
 */
namespace my\name
;
echo '<pre>';

$baz = function(){ return new \stdClass(); };
$spa = 'sp';
$spa .= 'a';
$foo = array('bar','baz',$baz(),$spa.'z'=>$baz());
if($foo['blah'] = $baz()){ // yes i mean that to be an assignment operator
    var_dump($foo['blah']);
}
function myFunc($arg1, $arg2 = "optionall"){
    if($arg1){
        $obj = new \stdClass();
    } else {
        $obj = false;
    }
    return $obj;
}
class MyClass {
    /**
     * testing 
     * 
     * @param mixed $arg 
     * @return PharData
     */
    public function testing($arg)
    {
        $returnVal = function(){ return 'barrrr'; };
        /*
         * samplereturn.php has:
         * <?php
         * namespace tester {
         *    return $returnVal();
         * }
         */
        $myFile = 'samplereturn.php';
        return include './'.$myFile;
    }
}
function makeObj(&$var){
    $var = new \stdClass();
}
function makeObj2($var){
    $var = new \stdClass();
}
$makeObj3 = function(&$var){
    $var = new \stdClass();
};
$myString = 'hello, world';
$myVar = myFunc("apple {$myString}");

$text = "Hello";
if ($text{0} == 'H') {
    $myVar = new MyClass();
    $test = $myVar->testing('arg');
    var_dump($test);
}


$tricky = 'string';
makeObj($tricky);
var_dump($tricky);

$tricky2 = 'string';
makeObj2(&$tricky2);
var_dump($tricky2);

$tricky3 = 'string';
$makeObj3($tricky3);
var_dump($tricky3);

$test = new \stdClass();
$test->foo = 'bar';
//$test-><tab> should show foo property as an option
$test = new \stdClass();
$test->bar = 'baz';
//$test->tab should show bar as an option


namespace my\name\testing;
var_dump(__NAMESPACE__);
