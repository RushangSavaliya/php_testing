<?php
// Create a TCP/IP socket
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP) or die("Could not create socket\n");

// Define the host and port
$host = "localhost";
$port = 8080;

// Bind the socket to the host and port
socket_bind($socket, $host, $port) or die("Could not bind to socket\n");

// Start listening for incoming connections
socket_listen($socket, 3) or die("Could not set up socket listener\n");

echo "Server started on $host:$port\n";

// Keep listening for new connections until the script is terminated
while (true) {
    // Accept an incoming connection
    $accept = socket_accept($socket) or die("Could not accept incoming connection\n");

    // Read data from the socket
    $message = socket_read($accept, 1024) or die("Could not read input\n");

    // Trim the message to remove any extra whitespace
    $message = trim($message);

    // Display the message from the client
    echo "Client says: " . $message . "\n";

    // Prompt the user to enter a response
    echo "Enter a response: ";
    $response = fgets(STDIN);

    // Send the response back to the client
    socket_write($accept, $response, strlen($response)) or die("Could not write output\n");

    // Close the connection with the current client
    socket_close($accept);
}

// Close the main socket (this line will never be reached in this script)
socket_close($socket);
?>
