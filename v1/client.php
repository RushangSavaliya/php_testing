<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client</title>
</head>

<body>
    <h1>Client</h1>
    <form action="" method="post">
        <label for="message">Message:</label>
        <input type="text" name="message" id="message">
        <input type="submit" value="Send">
    </form>
</body>

</html>

<?php

// Check if the message is set and not empty
if (!isset($_POST['message']) || $_POST['message'] == "") {
    exit();
}

// Create a socket
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP) or die("Could not create socket\n");

// Define the host and port
$host = "localhost";
$port = 8080;

// Connect to the server
socket_connect($socket, $host, $port) or die("Could not connect to server\n");

// Send a message to the server
$message = $_POST['message'];
socket_write($socket, $message, strlen($message)) or die("Could not send data to server\n");

//read the server's response
$response = socket_read($socket, 1024) or die("Could not read server response\n");
//trim the response
$response = trim($response);
//display the response
echo "Server says: " . $response . "\n";

//close the socket
socket_close($socket);
?>