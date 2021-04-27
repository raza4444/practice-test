<?php 
require "./Compass-PHP/Compass.php";

$data = CompassApi::logBatch(
    [
         ['test_event'],
        ['user@test.com','test_event2'],
         ['user2@test.com','test_event3',['_type'=>'error','name'=>'John Doe','company'=>'InnerTrends']]
    ]
 );
 print_r($data);
?>