<?php
// Define the server host and port
$host = '127.0.0.1';
$port = 8080;

// Create a TCP/IP socket
$serverSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if (!$serverSocket) {
    die("Error creating socket: " . socket_strerror(socket_last_error()) . "\n");
}

// Allow the port to be reused
socket_set_option($serverSocket, SOL_SOCKET, SO_REUSEADDR, 1);

// Bind the socket to the specified host and port
if (!socket_bind($serverSocket, $host, $port)) {
    die("Error binding socket: " . socket_strerror(socket_last_error()) . "\n");
}

// Start listening for incoming connections
if (!socket_listen($serverSocket)) {
    die("Error listening on socket: " . socket_strerror(socket_last_error()) . "\n");
}

echo "Server started on $host:$port\n";

// Array to hold connected client sockets
$clients = [];

while (true) {
    // Prepare an array of sockets to check for incoming data
    $readSockets = $clients;
    $readSockets[] = $serverSocket; // Include the main server socket

    // Initialize arrays for write and exceptions (not used here)
    $writeSockets = [];
    $exceptSockets = [];

    // Use socket_select to wait for any activity on the sockets
    if (socket_select($readSockets, $writeSockets, $exceptSockets, 0, 10) < 1) {
        continue; // No activity; continue looping
    }

    // Check if there's a new connection on the server socket
    if (in_array($serverSocket, $readSockets)) {
        $newSocket = socket_accept($serverSocket);
        if ($newSocket !== false) {
            $clients[] = $newSocket; // Add new client to the clients array

            // Read the client's handshake request headers
            $header = socket_read($newSocket, 1024);
            // Perform the WebSocket handshake (protocol upgrade)
            handshake($newSocket, $header);
            // Send a welcome message to the new client
            socket_write($newSocket, mask("Welcome to the WebSocket server!"));
        }
        // Remove the server socket from the read list
        $serverSocketKey = array_search($serverSocket, $readSockets);
        unset($readSockets[$serverSocketKey]);
    }

    // Loop through the sockets that have data
    foreach ($readSockets as $clientSocket) {
        // Read data from the client socket
        $data = @socket_read($clientSocket, 1024);
        if (!$data) {
            // If no data, the client has disconnected
            $clientKey = array_search($clientSocket, $clients);
            unset($clients[$clientKey]);
            socket_close($clientSocket);
            continue;
        }
        // Decode the masked message from the client
        $message = unmask($data);
        echo "Received: $message\n";
        // Broadcast the message to all connected clients
        foreach ($clients as $client) {
            socket_write($client, mask("Echo: " . $message));
        }
    }
}

// Close the main server socket when finished
socket_close($serverSocket);




/**
 * Perform the WebSocket handshake with the client.
 *
 * @param resource $client The client socket.
 * @param string   $header The HTTP headers from the client.
 */
function handshake($client, $header) {
    // Extract the Sec-WebSocket-Key from the headers using a regular expression
    preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $header, $matches);
    $key = trim($matches[1]);

    // Create the Sec-WebSocket-Accept key using the GUID as per the protocol
    $acceptKey = base64_encode(pack('H*', sha1($key . "258EAFA5-E914-47DA-95CA-C5AB0DC85B11")));

    // Prepare the upgrade headers required by the protocol
    $upgrade = "HTTP/1.1 101 Switching Protocols\r\n" .
               "Upgrade: websocket\r\n" .
               "Connection: Upgrade\r\n" .
               "Sec-WebSocket-Accept: $acceptKey\r\n\r\n";

    // Send the handshake response to the client
    socket_write($client, $upgrade);
}

/**
 * Unmask (decode) a WebSocket message received from the client.
 *
 * @param string $payload The masked payload from the client.
 * @return string         The unmasked (decoded) message.
 */
function unmask($payload) {
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
    $text = '';
    for ($i = 0; $i < strlen($data); $i++) {
        $text .= $data[$i] ^ $masks[$i % 4];
    }
    return $text;
}

/**
 * Mask (encode) a message to be sent to the client.
 *
 * @param string $text The plain text message.
 * @return string      The masked message following the WebSocket framing.
 */
function mask($text) {
    $b1 = 0x81; // 0x81 indicates a final text frame
    $length = strlen($text);
    if ($length <= 125) {
        $header = pack('CC', $b1, $length);
    } elseif ($length > 125 && $length < 65536) {
        $header = pack('CCn', $b1, 126, $length);
    } else {
        $header = pack('CCNN', $b1, 127, $length);
    }
    return $header . $text;
}
?>
