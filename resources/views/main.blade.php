<!doctype html>
<html lang="{{ app()->getLocale() }}">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="{{url('/css/main.css')}}">
        <script src="{{url('/js/main.js')}}"></script>
        <script src="{{url('/js/libs/masonry.js')}}"></script>

{{--FONTS--}}

{{--        <link href="https://fonts.googleapis.com/css2?family=Permanent+Marker&display=swap" rel="stylesheet">--}}
{{--        <link href="https://fonts.googleapis.com/css2?family=Potta+One&display=swap" rel="stylesheet">--}}
{{--        <link href="https://fonts.googleapis.com/css2?family=Hachi+Maru+Pop&display=swap" rel="stylesheet">--}}
{{--        <link href="https://fonts.googleapis.com/css2?family=Indie+Flower&display=swap" rel="stylesheet">--}}
{{--        <link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+128+Text&display=swap" rel="stylesheet">--}}
{{--        <link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@300&display=swap" rel="stylesheet">--}}
{{--        <link href="https://fonts.googleapis.com/css2?family=Libre+Baskerville&display=swap" rel="stylesheet">--}}
{{--        <link href="https://fonts.googleapis.com/css2?family=Gidugu&display=swap" rel="stylesheet">--}}
{{--        <link href="https://fonts.googleapis.com/css2?family=Sedgwick+Ave+Display&display=swap" rel="stylesheet">--}}
{{--        <link href="https://fonts.googleapis.com/css2?family=Nosifer&display=swap" rel="stylesheet">--}}
{{--        <link href="https://fonts.googleapis.com/css2?family=Libre+Caslon+Display&display=swap" rel="stylesheet">--}}
{{--        <link href="https://fonts.googleapis.com/css2?family=Sedgwick+Ave+Display&display=swap" rel="stylesheet">--}}


        <title>Melancholy Eriscal</title>

    </head>
    <body>
    <a href="/gallery/view">GALLERY</a>

{{--        <header>--}}
{{--            <h1 class="title glow">Melancholy Eriscal</h1>--}}
{{--        </header>--}}
{{--        <div class="wrapper clearfix">--}}
{{--            <aside id="category_nav">--}}
{{--                <ul id="category_list" class="nav_list">--}}
{{--                    @foreach($categories as $category)--}}
{{--                    <li class="category_item nav_item"><a href="?category={{$category->route}}&type={{$currentType}}">{{$category->name}}</a></li>--}}
{{--                    @endforeach--}}
{{--                </ul>--}}
{{--            </aside>--}}
{{--            <main>--}}
{{--                <nav id="product_nav">--}}
{{--                    <ul id="product_list" class="nav_list">--}}
{{--                        @foreach($productsTypes as $productType)--}}
{{--                        <li class="product_item nav_item"><a href="?category={{$currentCategory}}&type={{$productType->route    }}">{{$productType->name}}</a></li>--}}
{{--                        @endforeach--}}
{{--                    </ul>--}}
{{--                </nav>--}}
{{--                <div id="product_container" class="grid">--}}
{{--                    @foreach($products as $product)--}}
{{--                    <div class="product grid-item">--}}

{{--                        <img class="product_poster" src={{url('/img/posts/' . $product->cover_img)}} alt="" width="100%">--}}
{{--                        <div class="product_info">--}}
{{--                            <p class="product_name">{{$product->name}}</p>--}}
{{--                            <p class="product_description">{{$product->description}}</p>--}}
{{--                        </div>--}}

{{--                    </div>--}}
{{--                    @endforeach--}}
{{--                </div>--}}
{{--            </main>--}}
{{--        </div>--}}
{{--        <footer>--}}
{{--            <p>This is footer</p>--}}
{{--        </footer>--}}
    </body>
</html>
