<?php

// Define the host and port
$host = '127.0.0.1'; 
$port = 8080;        

// Create a socket
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

// Set socket options (This sets the socket option to reuse the address)
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

// Bind the socket to the address and port
socket_bind($socket, $host, $port);

// Start listening for connections
socket_listen($socket);

// Create an array to store the connected clients
$clients = []; 

echo "WebSocket server started on $host:$port\n";

// Keep listening for connections
while (true) {

    // Create an array of sockets to listen to
    $changedSockets = $clients;

    // Add the main socket to the array
    $changedSockets[] = $socket;

    // Create an array of sockets to listen to
    $write = [];
    $except = [];

    // Listen to the sockets
    socket_select($changedSockets, $write, $except, 0, 10);

    // Check if there is a new connection
    if (in_array($socket, $changedSockets)) {
        // Accept the new connection
        $newSocket = socket_accept($socket);
        // Add the new socket to the clients array
        $clients[] = $newSocket;
        // Set handshake flag to false
        $handshake = false;
        echo "New client connected\n";
        // Remove the main socket from the changed sockets array
        $socketKey = array_search($socket, $changedSockets);
        unset($changedSockets[$socketKey]);
    }

    // Loop through all changed sockets
    foreach ($changedSockets as $clientSocket) {
        // Receive data from the socket
        $data = @socket_recv($clientSocket, $buffer, 1024, 0);
        // Check if the client has disconnected
        if ($data === false || $data == 0) {
            echo "client disconnected\n";
            // Remove the client from the clients array
            $clientKey = array_search($clientSocket, $clients);
            unset($clients[$clientKey]);
            // Close the socket
            socket_close($clientSocket);
            continue;
        }

        // Perform the handshake if not already done
        if (!$handshake) {
            performHandshake($clientSocket, $buffer);
            $handshake = true;
        } else {
            // Unmask the received message
            $message = unmask($buffer);
            if (!empty($message)) {
                echo "Received: $message\n";
                // Send the message to all connected clients except the sender
                foreach ($clients as $client) {
                    if ($client != $clientSocket) {
                        sendMessage($client, $message);
                    }
                }
            }
        }
    }
}

// Function to perform the WebSocket handshake
function performHandshake($clientSocket, $headers) {
    // Parse the headers
    $headers = parseHeaders($headers);
    // Get the Sec-WebSocket-Key from the headers
    $secKey = $headers['Sec-WebSocket-Key'];
    // Create the Sec-WebSocket-Accept key
    $secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
    // Create the handshake response
    $handshakeResponse = "HTTP/1.1 101 Switching Protocols\r\n" .
        "Upgrade: websocket\r\n" .
        "Connection: Upgrade\r\n" .
        "Sec-WebSocket-Accept: $secAccept\r\n\r\n";
    // Send the handshake response
    socket_write($clientSocket, $handshakeResponse, strlen($handshakeResponse));
}

// Function to parse HTTP headers
function parseHeaders($headers) {
    // Split the headers into an array
    $headers = explode("\r\n", $headers);
    $headerArray = [];
    // Loop through each header
    foreach ($headers as $header) {
        // Split the header into key and value
        $parts = explode(": ", $header);
        if (count($parts) === 2) {
            $headerArray[$parts[0]] = $parts[1];
        }
    }
    return $headerArray;
}

// Function to unmask the received message
function unmask($payload)
{
    // Get the length of the payload
    $length = ord($payload[1]) & 127;
    if ($length == 126) {
        $masks = substr($payload, 4, 4);
        $data = substr($payload, 8);
    } elseif ($length == 127) {
        $masks = substr($payload, 10, 4);
        $data = substr($payload, 14);
    } else {
        $masks = substr($payload, 2, 4);
        $data = substr($payload, 6);
    }
    $unmaskedtext = '';
    // Unmask the data
    for ($i = 0; $i < strlen($data); ++$i) {
        $unmaskedtext .= $data[$i] ^ $masks[$i % 4];
    }
    return $unmaskedtext;
}

// Function to send a message to the client
function sendMessage($clientSocket, $message)
{
    // Mask the message
    $message = mask($message);
    // Send the message
    socket_write($clientSocket, $message, strlen($message));
}

// Function to mask the message
function mask($message)
{
    $frame = [];
    $frame[0] = 129;

    $length = strlen($message);
    if ($length <= 125) {
        $frame[1] = $length;
    } elseif ($length <= 65535) {
        $frame[1] = 126;
        $frame[2] = ($length >> 8) & 255;
        $frame[3] = $length & 255;
    } else {
        $frame[1] = 127;
        $frame[2] = ($length >> 56) & 255;
        $frame[3] = ($length >> 48) & 255;
        $frame[4] = ($length >> 40) & 255;
        $frame[5] = ($length >> 32) & 255;
        $frame[6] = ($length >> 24) & 255;
        $frame[7] = ($length >> 16) & 255;
        $frame[8] = ($length >> 8) & 255;
        $frame[9] = $length & 255;
    }

    // Convert the message to an array of ASCII values
    foreach (str_split($message) as $char) {
        $frame[] = ord($char);
    }

    // Convert the ASCII values back to characters and return the frame
    return implode(array_map('chr', $frame));
}
?>