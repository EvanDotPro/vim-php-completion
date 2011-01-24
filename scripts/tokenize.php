#!/usr/bin/php
<?php
/**
 * I'm not sure exactly where I'm going with this.
 */
$source = file_get_contents($argv[1]);
$tokens = token_get_all($source);
$tags = array();
$tags[] = '<T_OPEN_TAG>';
$openTags = array();
for($i=0; $i<count($tokens); $i++){
    if(is_string($tokens[$i])){


    } elseif(is_array($tokens[$i])){
        $token = token_name($tokens[$i][0]);
        $tokens[$i][3] = $token;
        if(isset($openTags[$tokens[$i][0]]) && count($openTags[$tokens[$i][0]]) > 0){
            $tag = array_pop($openTags[$tokens[$i][0]]);
            $tags[] = "</{$tag}>";
        }
        switch($tokens[$i][0]){
            case T_NAMESPACE:
                $namespace = '';
                $openTags[$tokens[$i][0]][] = $token;
                do {
                    $i++;
                    if($tokens[$i][0] == T_STRING || $tokens[$i][0] == T_NS_SEPARATOR){
                        $namespace .= $tokens[$i][1];
                    }
                } while ($tokens[$i][0] == T_WHITESPACE || $tokens[$i][0] == T_NS_SEPARATOR || $tokens[$i][0] == T_STRING);
                $tags[] = '<'.$token.' name="'.$namespace.'">';
                break;
            case T_VARIABLE:

                break;
        }

    } else {
        die('wtf?');
    }
}
// close unclosed tags
foreach($openTags as $openTag){
    foreach($openTag as $tag){
        $tags[] = "</{$tag}>";
    }
}
$tags[] = '</T_OPEN_TAG>';
print_r($tags);
print_r($tokens);
