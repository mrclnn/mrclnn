<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>WheatART</title>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="{{url('/js/portfolio.js?t=' . time())}}"></script>
    <link rel="stylesheet" href="{{url('/css/portfolio.css?t=' . time())}}">
    <script type="module" src="https://unpkg.com/@google/model-viewer/dist/model-viewer.min.js"></script>
</head>
<body>
    <header>
        <h2>WheatART inc.</h2>
        <nav>
            <a href="">works</a>
            <a href="">info</a>
            <a href="">contacts</a>
            <a href="">shop</a>
        </nav>
    </header>
    <main>
        <div id="description">
            <h1>Шаман ля охуенный погляди</h1>
            <p>Не придумал</p>
            <div id="other-works">
                <img src="{{url('/models/covers/Horse.jpg?t')}}" alt="" data-name="Horse with osteohondroze" data-desc="еще не придумал">
{{--                <img src="{{url('/models/covers/RobotExpressive.jpg?t')}}" alt="" class="chosen">--}}
                <img src="{{url('/models/covers/sham.jpg')}}" alt="" data-name="Шаман ля охуенный погляди" data-desc="Такой и отпиздохать может и даже не запыхается, дед в полном расцвете сил, дед это эфир, дух земли">
                <img src="{{url('/models/covers/chest.jpg')}}" data-name="Можно сложить хуйню" alt="" data-desc="еще не придумал">
                <img src="{{url('/models/covers/muffin.jpg')}}" data-name="Так бы и съел ОАОМОМОМОРЛВОАЫ" alt="" data-desc="еще не придумал">
            </div>
        </div>
        <div id="model">
{{--            <model-viewer src="shared-assets/models/Astronaut.glb" alt="A 3D model of an astronaut" ar ar-modes="webxr scene-viewer quick-look" environment-image="neutral" auto-rotate camera-controls></model-viewer>--}}
            <model-viewer src="{{url('/models/sham.glb?t='.time())}}" auto-rotate camera-controls ar ar-modes="webxr scene-viewer quick-look" shadow-intensity="1" autoplay>

            </model-viewer>
        </div>
    </main>
    <footer>
        <p>Все права защищены спасибо пожалуйста</p>
        <form enctype="multipart/form-data" action="https://mrclnn.com/tmp.php" method="POST">
            <!-- Поле MAX_FILE_SIZE должно быть указано до поля загрузки файла -->
            <input type="hidden" name="MAX_FILE_SIZE" value="300000000" />
            <!-- Название элемента input определяет имя в массиве $_FILES -->
            тест: <input name="userfile" type="file" />
            <input type="submit" value="Отправить файл" />
        </form>
    </footer>
</body>
</html>