<?php
// Constants
const STORAGE_PATH = __DIR__ . '/chat_sessions';
const KEY_LENGTH = 16;
const ENCRYPTION_METHOD = 'aes-256-cbc';

// Ensure the storage directory exists
if (!is_dir(STORAGE_PATH)) {
    mkdir(STORAGE_PATH, 0777, true);
}

// Helper Functions
function generateRandomKey() {
    return bin2hex(random_bytes(KEY_LENGTH));
}

function encryptMessage($key, $message) {
    $iv = random_bytes(openssl_cipher_iv_length(ENCRYPTION_METHOD));
    $encrypted = openssl_encrypt($message, ENCRYPTION_METHOD, $key, 0, $iv);
    return base64_encode($iv . $encrypted);
}

function decryptMessage($key, $encryptedMessage) {
    $data = base64_decode($encryptedMessage);
    $ivLength = openssl_cipher_iv_length(ENCRYPTION_METHOD);
    $iv = substr($data, 0, $ivLength);
    $encrypted = substr($data, $ivLength);
    return openssl_decrypt($encrypted, ENCRYPTION_METHOD, $key, 0, $iv);
}

function createChatSession() {
    $key = generateRandomKey();
    $filePath = STORAGE_PATH . "/$key.json";

    $data = [
        'messages' => []
    ];

    file_put_contents($filePath, json_encode($data));
    return $key;
}

function validateKey($key) {
    $filePath = STORAGE_PATH . "/$key.json";
    return file_exists($filePath);
}

function saveMessage($key, $message) {
    $filePath = STORAGE_PATH . "/$key.json";

    if (!file_exists($filePath)) {
        return false;
    }

    $data = json_decode(file_get_contents($filePath), true);
    $encryptedMessage = encryptMessage($key, $message);

    $data['messages'][] = [
        'message' => $encryptedMessage,
        'timestamp' => date('Y-m-d H:i:s')
    ];

    file_put_contents($filePath, json_encode($data));

    // Decrypt for returning
    foreach ($data['messages'] as &$msg) {
        $msg['message'] = decryptMessage($key, $msg['message']);
    }

    return $data['messages'];
}

function getMessages($key) {
    $filePath = STORAGE_PATH . "/$key.json";

    if (!file_exists($filePath)) {
        return [];
    }

    $data = json_decode(file_get_contents($filePath), true);

    // Decrypt messages
    foreach ($data['messages'] as &$msg) {
        $msg['message'] = decryptMessage($key, $msg['message']);
    }

    return $data['messages'] ?? [];
}

function deleteChatSession($key) {
    $filePath = STORAGE_PATH . "/$key.json";

    if (file_exists($filePath)) {
        unlink($filePath);
        return true;
    }

    return false;
}

// Handle Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'generateKey') {
        echo json_encode(['key' => createChatSession()]);
        exit;
    }

    if ($action === 'joinChat') {
        $key = $_POST['key'] ?? '';
        echo json_encode(['success' => validateKey($key)]);
        exit;
    }

    if ($action === 'sendMessage') {
        $key = $_POST['key'] ?? '';
        $message = htmlspecialchars($_POST['message'] ?? '');
        $messages = saveMessage($key, $message);
        echo json_encode(['messages' => $messages]);
        exit;
    }

    if ($action === 'deleteChat') {
        $key = $_POST['key'] ?? '';
        $success = deleteChatSession($key);
        echo json_encode(['success' => $success]);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'fetchMessages') {
        $key = $_GET['key'] ?? '';
        echo json_encode(['messages' => getMessages($key)]);
        exit;
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Chat Room</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white">
    <div class="flex flex-col items-center justify-center min-h-screen">
        <h1 class="text-3xl font-bold mb-4">Secure Chat Room</h1>
        <div id="keySection" class="w-full max-w-md bg-gray-800 p-6 rounded-lg shadow-md">
            <button 
                id="generateKeyButton" 
                class="w-full py-2 bg-blue-500 rounded hover:bg-blue-700 transition mb-4">Generate Key</button>
            <form id="joinChatForm">
                <label for="roomKey" class="block text-sm font-medium">Chat Room Key</label>
                <input 
                    type="text" 
                    name="roomKey" 
                    id="roomKey" 
                    class="w-full mt-2 p-2 rounded bg-gray-700 text-white focus:ring-2 focus:ring-blue-500"
                    placeholder="Enter chat key" required>
                <button 
                    type="submit" 
                    class="w-full mt-4 py-2 bg-green-500 rounded hover:bg-green-700 transition">Join Chat</button>
            </form>
        </div>
        <div id="chatSection" class="hidden w-full max-w-2xl bg-gray-800 p-6 rounded-lg shadow-md">
            <h2 class="text-xl font-semibold mb-4">Chat Room</h2>
            <div id="chatBox" class="h-64 overflow-y-auto bg-gray-700 p-4 rounded mb-4"></div>
            <form id="messageForm">
                <input 
                    type="text" 
                    id="messageInput" 
                    class="w-full p-2 rounded bg-gray-600 text-white focus:ring-2 focus:ring-blue-500"
                    placeholder="Type your message..." required>
                <button 
                    type="submit" 
                    class="mt-2 py-2 px-4 bg-green-500 rounded hover:bg-green-700 transition">Send</button>
                <button 
                    type="button" 
                    id="deleteButton" 
                    class="mt-2 py-2 px-4 bg-red-500 rounded hover:bg-red-700 transition">Delete Chat</button>
            </form>
        </div>
    </div>
    <script>
        const keySection = document.getElementById('keySection');
        const chatSection = document.getElementById('chatSection');
        const chatBox = document.getElementById('chatBox');
        const generateKeyButton = document.getElementById('generateKeyButton');
        const joinChatForm = document.getElementById('joinChatForm');
        const messageForm = document.getElementById('messageForm');
        const deleteButton = document.getElementById('deleteButton');
        const messageInput = document.getElementById('messageInput');
        const roomKeyInput = document.getElementById('roomKey');

        let roomKey = '';
        let messages = [];

        function renderMessages() {
            chatBox.innerHTML = '';
            messages.forEach(msg => {
                const messageDiv = document.createElement('div');
                messageDiv.textContent = `[${msg.timestamp}] ${msg.message}`;
                messageDiv.classList.add('p-2', 'mb-2', 'bg-gray-600', 'rounded');
                
                const timestamp = document.createElement('span');
                timestamp.textContent = msg.timestamp;
                timestamp.classList.add('absolute', 'right-2', 'bottom-2', 'text-xs', 'text-gray-400');
                messageDiv.appendChild(timestamp);
                chatBox.appendChild(messageDiv);
            });
            chatBox.scrollTop = chatBox.scrollHeight;
        }



        generateKeyButton.addEventListener('click', () => {
            fetch('', { method: 'POST', body: new URLSearchParams({ action: 'generateKey' }) })
                .then(res => res.json())
                .then(data => {
                    alert(`Generated Key: ${data.key}`);
                    roomKeyInput.value = data.key;
                });
        });

        joinChatForm.addEventListener('submit', e => {
            e.preventDefault();
            roomKey = roomKeyInput.value;

            fetch('', {
                method: 'POST',
                body: new URLSearchParams({ action: 'joinChat', key: roomKey })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        keySection.classList.add('hidden');
                        chatSection.classList.remove('hidden');
                        fetchMessages();
                    } else {
                        alert('Invalid Key');
                    }
                });
        });

        messageForm.addEventListener('submit', e => {
            e.preventDefault();
            const message = messageInput.value;

            fetch('', {
                method: 'POST',
                body: new URLSearchParams({ action: 'sendMessage', key: roomKey, message })
            })
                .then(res => res.json())
                .then(data => {
                    messages = data.messages;
                    renderMessages();
                });

            messageInput.value = '';
        });

        deleteButton.addEventListener('click', () => {
            fetch('', {
                method: 'POST',
                body: new URLSearchParams({ action: 'deleteChat', key: roomKey })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert('Chat Deleted');
                        location.reload();
                    }
                });
        });

        function fetchMessages() {
            fetch(`?action=fetchMessages&key=${roomKey}`)
                .then(res => res.json())
                .then(data => {
                    messages = data.messages;
                    renderMessages();
                });
        }

        setInterval(fetchMessages, 2000);
    </script>
</body>
</html>
