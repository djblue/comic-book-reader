<?php if (!defined('ACTIVE')) exit('No direct script access allowed');

// Nice wrapper for Slim and sqlite.
// - sets p default rest routes

class Resource {

    private $app;
    public static $db;

    public function __construct ($app) {
        $this->app = $app;
    }

    public function get($model) {

        $app = $this->app;
        $db = self::$db;

        return function () use ($app, $db, $model) {

            $array = [];

            $query = $db->prepare('SELECT * FROM ' . $model);
            $result = $query->execute();

            $response = $app->response();
            $response['Content-Type'] = 'application/json';
            $response['X-Powered-By'] = 'Slim';
            $response->status(200);

            while($row = $result->fetchArray(SQLITE3_ASSOC)) {

                $array[] = $row;
            }

            $response->body(json_encode($array));
        };
             
    }
}

Resource::$db = new SQLite3("db/cbviewer.db");
