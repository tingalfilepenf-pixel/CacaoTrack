# Illuminate\Database\QueryException - Internal Server Error

SQLSTATE[HY000] [1045] Access denied for user 'root'@'localhost' (using password: NO) (Connection: mysql, Host: 127.0.0.1, Port: 3306, Database: db_capstone, SQL: select * from `sessions` where `id` = LulBELEY5YNMzwzP62d9g2ICzSh0xDTxAmDNSVst limit 1)

PHP 8.3.29
Laravel 13.30.0
127.0.0.1:8000

## Stack Trace

0 - vendor\laravel\framework\src\Illuminate\Database\Connection.php:857
1 - vendor\laravel\framework\src\Illuminate\Database\Connection.php:1038
2 - vendor\laravel\framework\src\Illuminate\Database\Connection.php:1017
3 - vendor\laravel\framework\src\Illuminate\Database\Connection.php:815
4 - vendor\laravel\framework\src\Illuminate\Database\Connection.php:426
5 - vendor\laravel\framework\src\Illuminate\Database\Query\Builder.php:3629
6 - vendor\laravel\framework\src\Illuminate\Database\Query\Builder.php:3613
7 - vendor\laravel\framework\src\Illuminate\Database\Concerns\BuildsQueries.php:366
8 - vendor\laravel\framework\src\Illuminate\Database\Query\Builder.php:3531
9 - vendor\laravel\framework\src\Illuminate\Session\DatabaseSessionHandler.php:96
10 - vendor\laravel\framework\src\Illuminate\Session\Store.php:128
11 - vendor\laravel\framework\src\Illuminate\Session\Store.php:116
12 - vendor\laravel\framework\src\Illuminate\Session\Store.php:100
13 - vendor\laravel\framework\src\Illuminate\Session\Middleware\StartSession.php:146
14 - vendor\laravel\framework\src\Illuminate\Support\helpers.php:393
15 - vendor\laravel\framework\src\Illuminate\Session\Middleware\StartSession.php:143
16 - vendor\laravel\framework\src\Illuminate\Session\Middleware\StartSession.php:115
17 - vendor\laravel\framework\src\Illuminate\Session\Middleware\StartSession.php:63
18 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:219
19 - vendor\laravel\framework\src\Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse.php:36
20 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:219
21 - vendor\laravel\framework\src\Illuminate\Cookie\Middleware\EncryptCookies.php:74
22 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:219
23 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:137
24 - vendor\laravel\framework\src\Illuminate\Routing\Router.php:821
25 - vendor\laravel\framework\src\Illuminate\Routing\Router.php:800
26 - vendor\laravel\framework\src\Illuminate\Routing\Router.php:764
27 - vendor\laravel\framework\src\Illuminate\Routing\Router.php:753
28 - vendor\laravel\framework\src\Illuminate\Foundation\Http\Kernel.php:200
29 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:180
30 - vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\TransformsRequest.php:21
31 - vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull.php:31
32 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:219
33 - vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\TransformsRequest.php:21
34 - vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\TrimStrings.php:51
35 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:219
36 - vendor\laravel\framework\src\Illuminate\Http\Middleware\ValidatePostSize.php:27
37 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:219
38 - vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance.php:110
39 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:219
40 - vendor\laravel\framework\src\Illuminate\Http\Middleware\HandleCors.php:61
41 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:219
42 - vendor\laravel\framework\src\Illuminate\Http\Middleware\TrustProxies.php:58
43 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:219
44 - vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\InvokeDeferredCallbacks.php:22
45 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:219
46 - vendor\laravel\framework\src\Illuminate\Http\Middleware\ValidatePathEncoding.php:28
47 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:219
48 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:137
49 - vendor\laravel\framework\src\Illuminate\Foundation\Http\Kernel.php:175
50 - vendor\laravel\framework\src\Illuminate\Foundation\Http\Kernel.php:144
51 - vendor\laravel\framework\src\Illuminate\Foundation\Application.php:1227
52 - public\index.php:20
53 - vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php:23

## Previous exception

### 1. PDOException

SQLSTATE[HY000] [1045] Access denied for user 'root'@'localhost' (using password: NO)

0 - vendor\laravel\framework\src\Illuminate\Database\Connectors\Connector.php:66
1 - vendor\laravel\framework\src\Illuminate\Database\Connectors\Connector.php:66
2 - vendor\laravel\framework\src\Illuminate\Database\Connectors\Connector.php:85
3 - vendor\laravel\framework\src\Illuminate\Database\Connectors\Connector.php:48
4 - vendor\laravel\framework\src\Illuminate\Database\Connectors\MySqlConnector.php:24
5 - vendor\laravel\framework\src\Illuminate\Database\Connectors\ConnectionFactory.php:220
6 - vendor\laravel\framework\src\Illuminate\Database\Connection.php:1298
7 - vendor\laravel\framework\src\Illuminate\Database\Connection.php:542
8 - vendor\laravel\framework\src\Illuminate\Database\Connection.php:435
9 - vendor\laravel\framework\src\Illuminate\Database\Connection.php:846
10 - vendor\laravel\framework\src\Illuminate\Database\Connection.php:1038
11 - vendor\laravel\framework\src\Illuminate\Database\Connection.php:1017
12 - vendor\laravel\framework\src\Illuminate\Database\Connection.php:815
13 - vendor\laravel\framework\src\Illuminate\Database\Connection.php:426
14 - vendor\laravel\framework\src\Illuminate\Database\Query\Builder.php:3629
15 - vendor\laravel\framework\src\Illuminate\Database\Query\Builder.php:3613
16 - vendor\laravel\framework\src\Illuminate\Database\Concerns\BuildsQueries.php:366
17 - vendor\laravel\framework\src\Illuminate\Database\Query\Builder.php:3531
18 - vendor\laravel\framework\src\Illuminate\Session\DatabaseSessionHandler.php:96
19 - vendor\laravel\framework\src\Illuminate\Session\Store.php:128
20 - vendor\laravel\framework\src\Illuminate\Session\Store.php:116
21 - vendor\laravel\framework\src\Illuminate\Session\Store.php:100
22 - vendor\laravel\framework\src\Illuminate\Session\Middleware\StartSession.php:146
23 - vendor\laravel\framework\src\Illuminate\Support\helpers.php:393
24 - vendor\laravel\framework\src\Illuminate\Session\Middleware\StartSession.php:143
25 - vendor\laravel\framework\src\Illuminate\Session\Middleware\StartSession.php:115
26 - vendor\laravel\framework\src\Illuminate\Session\Middleware\StartSession.php:63
27 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:219
28 - vendor\laravel\framework\src\Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse.php:36
29 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:219
30 - vendor\laravel\framework\src\Illuminate\Cookie\Middleware\EncryptCookies.php:74
31 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:219
32 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:137
33 - vendor\laravel\framework\src\Illuminate\Routing\Router.php:821
34 - vendor\laravel\framework\src\Illuminate\Routing\Router.php:800
35 - vendor\laravel\framework\src\Illuminate\Routing\Router.php:764
36 - vendor\laravel\framework\src\Illuminate\Routing\Router.php:753
37 - vendor\laravel\framework\src\Illuminate\Foundation\Http\Kernel.php:200
38 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:180
39 - vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\TransformsRequest.php:21
40 - vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull.php:31
41 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:219
42 - vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\TransformsRequest.php:21
43 - vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\TrimStrings.php:51
44 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:219
45 - vendor\laravel\framework\src\Illuminate\Http\Middleware\ValidatePostSize.php:27
46 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:219
47 - vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance.php:110
48 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:219
49 - vendor\laravel\framework\src\Illuminate\Http\Middleware\HandleCors.php:61
50 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:219
51 - vendor\laravel\framework\src\Illuminate\Http\Middleware\TrustProxies.php:58
52 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:219
53 - vendor\laravel\framework\src\Illuminate\Foundation\Http\Middleware\InvokeDeferredCallbacks.php:22
54 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:219
55 - vendor\laravel\framework\src\Illuminate\Http\Middleware\ValidatePathEncoding.php:28
56 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:219
57 - vendor\laravel\framework\src\Illuminate\Pipeline\Pipeline.php:137
58 - vendor\laravel\framework\src\Illuminate\Foundation\Http\Kernel.php:175
59 - vendor\laravel\framework\src\Illuminate\Foundation\Http\Kernel.php:144
60 - vendor\laravel\framework\src\Illuminate\Foundation\Application.php:1227
61 - public\index.php:20
62 - vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php:23

## Request

GET /

## Headers

* **host**: 127.0.0.1:8000
* **connection**: keep-alive
* **cache-control**: max-age=0
* **sec-ch-ua**: "Brave";v="153", "Not_A Brand";v="8", "Chromium";v="153"
* **sec-ch-ua-mobile**: ?0
* **sec-ch-ua-platform**: "Windows"
* **upgrade-insecure-requests**: 1
* **user-agent**: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153.0.0.0 Safari/537.36
* **accept**: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8
* **sec-gpc**: 1
* **sec-fetch-site**: same-origin
* **sec-fetch-mode**: navigate
* **sec-fetch-user**: ?1
* **sec-fetch-dest**: document
* **accept-encoding**: gzip, deflate, br, zstd
* **accept-language**: en-GB,en;q=0.8

## Route Context

controller: \App\Http\Controllers\DashboardController@index
route name: dashboard
middleware: web, auth

## Route Parameters

No route parameter data available.

## Database Queries

No database queries detected.
