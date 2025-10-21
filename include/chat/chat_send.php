<?php
session_start();
require '../config.php';

$user_id = $_SESSION['user_id'];
$room = $_POST['room'];
$message = trim($_POST['message']);

if(!$message) exit;

if(strpos($room,'project_')===0){
    $project_id = str_replace('project_','',$room);
    $sql="INSERT INTO chat_messages (project_id,sender_id,message) VALUES (?,?,?)";
    $stmt=$conn->prepare($sql);
    $stmt->bind_param("iis",$project_id,$user_id,$message);
    $stmt->execute();
}
