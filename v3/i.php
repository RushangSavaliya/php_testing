<!DOCTYPE html>
<html>

<head>
    <title>WebSocket Test</title>
</head>

<body>
    <form onsubmit="sendMessage(); return false;">
        <input type="text" id="message" />
        <input type="submit" value="Send" />
    </form>

    <script>
        var conn = new WebSocket('ws://localhost:8080');
        conn.onopen = function (e) {
            console.log("Connection established!");
        };

        conn.onmessage = function (e) {
            console.log(e.data);
        };

        function sendMessage() {
            var messageInput = document.getElementById('message');
            conn.send(messageInput.value);
            messageInput.value = ''; // Clear the input box
        }
    </script>
</body>

</html>