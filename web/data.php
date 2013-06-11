<?php
    ini_set('display_errors', 'On');
    error_reporting(E_ALL);

    // Load the driver
    require_once("rdb/rdb.php");

    // Connect to localhost
    $conn = r\connect('localhost');

    // How many documents are in the table?
    $cursor = r\db("cakemeter")->table("stars")->filter( function($doc) {
        return $doc("repo")->eq("rethinkdb")->rOr($doc("repo")->eq("mongo"));
    })->groupedMapReduce(
            function ($doc) { return $doc('repo'); },
            function ($doc) { return r\expr(array($doc)); },
            function ($left, $right) {
                return $left->union($right);
            }
    )->map( function($group) {
        return r\expr(array(
            "repo" => $group("group"),
            "stars" => $group("reduction")->orderBy("time"),
        ));    
    })->run($conn);
    
    // Print output. We first have to convert in native PHP objects before using json_encode
    print_r(json_encode($cursor->toNative()));
?>
