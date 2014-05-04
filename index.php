<?php define('ACTIVE', true);

// Application Constants
define('BASE', dirname(__FILE__)."/");
define('ROOT', dirname($_SERVER['PHP_SELF']).'/');

define('CSS',  ROOT.'css/');
define('JS',   ROOT.'js/');
define('IMG',  ROOT.'img/');

// Set error reporting for development.
error_reporting(E_ALL);
ini_set('display_errors', 1);

require('config.php');
require('book.php');
require('vendor/autoload.php');


\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim();

$app->get('/', function () use ($app) { include('index.html'); });

$app->get('/api/check', function () use ($app) { 

    $check = [];
    $exts = ['gd', 'SQLite3', 'rar', 'zip'];

    foreach ($exts as $ext) {
        $check[$ext] = extension_loaded($ext);
    }

    $response = $app->response();
    $response['Content-Type'] = 'application/json';
    $response['X-Powered-By'] = 'Slim';
    $response->status(200);
    $response->body(json_encode($check));
});

$app->get('/api/scan', function () use ($app) { 
    Book::scan();
    $response = $app->response();
    $response['Content-Type'] = 'application/json';
    $response['X-Powered-By'] = 'Slim';
    $response->status(200);
    //$response->body(json_encode(Book::list_all_books()));
});


$app->get('/api/books', function () use ($app) {
    $res = $app->response();
    $res['Content-Type'] = 'application/json';
    $res->body(json_encode(Book::list_all_books()));
});

$app->get('/api/books/:id/:page_number', 

function ($id, $page_number = 0) use ($app) {

    $response = $app->response();
    
    // Construct a book with the supplied id.
    $book = new Book($id);             

    // Get page stream; default is zero if not specified.
    $stream = $book->get_page_stream($page_number);

    if ($stream == false) {
        $response->status(404);
        $response->write('File Not Found.');
        return;
    } 
        
    // Check width query perameters.
    $width   = ( isset($_GET['width']) and
                    is_numeric($_GET['width']) )?
                        intval($_GET['width']) : false;

    // Check quality query perameters.
    $quality = ( isset($_GET['quality']) and 
                    is_numeric($_GET['quality']) )? 
                        intval($_GET['quality']) : 30;

    // Create image from the found stream.
    $img = imagecreatefromstring(stream_get_contents($stream));

    
    if ($width != false) { // The width query was specified.

        // Calculate the height of the new image.
        $height = $width*(ImagesY($img)/ImagesX($img));
        // Create the new image with the supplied dimensions.
        $final = ImageCreateTrueColor($width, $height);
        // Copy resized imto into newly created image.
        imagecopyresized($final, $img, 0, 0, 0, 0, 
            $width+1, $height+1, ImagesX($img), ImagesY($img));

        // And then there was a page.
        //header("Content-Type: image/jpg");
        $app->response()['Content-Type'] = 'image/jpg';
        imagejpeg($final, NULL, $quality);
       

        // Clean up the memeories.
        ImageDestroy($img);
        ImageDestroy($final);

    } else { // Use the default width.

        // And then there was a page.
        // header("Content-Type: image/jpg");
        $app->response()['Content-Type'] = 'image/jpg';
        imagejpeg($img, NULL, $quality);

        // Clean up the memeories.
        ImageDestroy($img);
    }
});

/*
$app->get('/:table', function ($table) use ($app) {

    $db = new SQLite3('cache/cbviewer.db');

    try {

        $result = $db->query("SELECT `id`,`data` FROM $table");

        $array = [];

        while($row = $result->fetchArray(SQLITE3_ASSOC)) {

            $array[] = array_merge( ['id' => $row['id']],
                json_decode($row['data'], true));
        }

        $res = $app->response();
        $res['Content-Type'] = 'application/json'; 
        $res->body(json_encode($array));

    } catch (ErrorException $error) {

        print_r($error);
    }
});

$app->get('/:table/:id', function ($table, $id) use ($app) {

    $db = new SQLite3('cache/cbviewer.db');

    //try {

        $statement = $db->prepare("SELECT `id`,`data` FROM $table WHERE `id`=:id");
        $statement->bindValue(':id', $id);

        $row = $statement->execute()->fetchArray(SQLITE3_ASSOC);

        $result = array_merge( ['id' => $row['id']],
                json_decode($row['data'], true));


        $res = $app->response();
        $res['Content-Type'] = 'application/json'; 
        $res->body(json_encode($result));

    
    //} catch (ErrorException $error) {

     //   print_r($error);
    //}
});

$app->post('/:table', function ($table) use ($app) {

    $req = $app->request();
    $body = $req->getBody();


    if ($req->headers()['CONTENT_TYPE'] == 'application/json')
    {
        try {

            $db = new SQLite3('cache/cbviewer.db');

            $statement = $db->prepare("INSERT INTO $table 
                (`id`,`data`) VALUES ( :id, :data )");

            $statement->bindValue(':id', md5($body)); 
            $statement->bindValue(':data', $body);

            $statement->execute();
            

        } catch (ErrorException $error) {

            var_dump($error);

        }
    }
});

$app->delete('/:table/:id', function ($table, $id) use ($app) {

    $req = $app->request();

    $db = new SQLite3('cache/cbviewer.db');

    $statement = $db->prepare("DELETE FROM $table 
        WHERE `id`=:id LIMIT 1");

    $statement->bindValue(':id', $id); 

    print_r($statement->execute()->fetchArray(SQLITE3_ASSOC));
    
});
*/

$app->run();

