<?php
if(!isset($_GET['msuawg792809asag3aym8h9YIBDSI87eF0k'])){
//    echo $_GET['k'];
    echo 'access denied';
    die;
}

switch ($_GET['msuawg792809asag3aym8h9YIBDSI87eF0k']){
    case 'smgyaeiog98EGS98seggse8' :
        $mark = 'Artem';
        break;
    case 'sduh79GSEuhse9Usmye7ishgueGU' :
        $mark = 'Default';
        break;
    default:
        echo 'access denied';
        die;
}

addIPtoAllow($_SERVER['REMOTE_ADDR'], $mark);
echo '<h1>access granted</h1><br>';
//echo '<a href="/gallery/view">viewer</a>';

function addIPtoAllow(string $ip, string $mark){
    $htaccess = file_get_contents('.htaccess');
    if(strripos($htaccess, $ip)) return;
//    $id = uniqid('', true);
    $allowString = "allow from {$ip} #{$mark}#\n#place to allow ip#";
    $htaccess = str_replace('#place to allow ip#', $allowString, $htaccess);
    file_put_contents('.htaccess', $htaccess);
}