<?php
declare(strict_types=1);

require_once __DIR__.'/app.php';

final class Database
{
    private static ?PDO $instance=null;

    public static function getInstance(): PDO
    {
        if(self::$instance===null){
            $dsn='mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset='.DB_CHARSET;
            self::$instance=new PDO($dsn,DB_USER,DB_PASS,[
                PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES=>false,
            ]);
        }
        return self::$instance;
    }

    private function __construct(){}
}

function db(): PDO
{
    return Database::getInstance();
}
