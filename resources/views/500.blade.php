<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
    <style>
        @import url('https://fonts.googleapis.com/css?family=Lato|Roboto+Slab');

        * {
            position: relative;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body{
            background-color: #222;
            color: #bbb;
        }

        .centered {
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        h1 {
            margin-bottom: 30px;
            font-family: 'Lato', sans-serif;
            font-size: 50px;
        }

        .message {
            display: inline-block;
            line-height: 1.2;
            transition: line-height .2s, width .2s;
            overflow: hidden;
        }

        .message,
        .hidden {
            font-family: 'Roboto Slab', serif;
            font-size: 18px;
        }

        .hidden {
            color: #FFF;
        }
    </style>
</head>
<body>
    <section class="centered">
        <h1>500 Server Error</h1>
        <div class="container">
            <span class="message" id="js-whoops"></span> <span class="message" id="js-appears"></span> <span class="message" id="js-error"></span> <span class="message" id="js-apology"></span>
            <div><span id="js-hidden">Lena Leto broke it all again.</span></div>
        </div>
    </section>
</body>
</html>