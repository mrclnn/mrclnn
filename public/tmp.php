<?php

try{
//    $uploaddir = public_path('/models/');
    $uploadfile = '/home/u946280762/domains/mrclnn.com/public_html/models/sham.glb';

    if(strpos($_FILES['userfile']['name'], '.glb') === false){
        echo 'судя по всему загружаешь не .glb файл. пытаешься загрузить '.$_FILES['userfile']['name'].', так не пойдет, надо только .gbl ';
        return;
    }

    echo '<pre>';
    if (move_uploaded_file($_FILES['userfile']['tmp_name'], $uploadfile)) {
        echo "Все заебись, щас тя перекинет обратно\n";
        echo '<script>
setTimeout(function(){window.location.href = "https://mrclnn.com/portfolio";}, 1000)
//window.location.href = "https://mrclnn.com/portfolio";
</script>';
    } else {
        if($_FILES['userfile']['error'] === 4){
            echo "вобщем ты не загрузила файл, надо его сначала выбрать а потом уже тыкать загрузить, епта.";
            return;
        } elseif($_FILES['userfile']['error'] === 2){
            echo 'превышен максимальный размер файла, короче надо дернуть меня чтобы я опять увеличил лимит, но так то это уже охуеть какой большой файл';
            return;
        }
        echo "случилась залупа напиши мне в телеге то что ниже:<br><br><br><br><br><br><br><br>";
        print_r($_FILES);


    }

//    echo 'Некоторая отладочная информация:';


    print "</pre>";
} catch (Throwable $e){
    echo "случилась ТОТАЛЬНАЯ ЗАЛУПА напиши мне в телеге сообщение, что будет ниже:<br><br><br><br><br><br><br><br><br>";
    var_dump($e);
}
