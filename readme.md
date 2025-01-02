
# Secure End-to-End Encrypted Chat

## Overview

This is a secure, privacy-oriented chat application that operates without a database, using only encrypted messages stored temporarily in JSON files. The encryption ensures that data is encrypted on the server side and only decrypted on the client side. Each user generates a unique key to join the chat session, and all communication is encrypted using **AES-256-CBC**. 

The chat history (encrypted messages) is stored in a JSON file on the server and automatically deleted after each chat session to maintain privacy. This ensures that messages are never stored in plaintext and the server has no knowledge of the actual content of the messages.

## Features

- **End-to-End Encryption**: Messages are encrypted with **AES-256-CBC** on the server and only decrypted on the client side.
- **Key Generation**: A random key is generated for each chat session. Users must use this key to join the session.
- **No Database**: Messages are stored temporarily in encrypted JSON files, eliminating the need for a database.
- **Automatic Cleanup**: A cron job deletes all encrypted JSON files every minute, ensuring no data lingers on the server.
- **Chat Room Deletion**: Users can manually delete chat sessions using a "Delete Chat" button.


## Encryption Details

### Key Generation

The application generates a unique random key for each chat session using PHP's `random_bytes()` function. This 16-byte key is used with AES-256-CBC for encryption.

```php
function generateRandomKey() {
    return bin2hex(random_bytes(KEY_LENGTH));  // Generates a random 16-byte key
}
```

### Message Encryption

Messages are encrypted using AES-256-CBC with a randomly generated initialization vector (IV) for each encryption.

```php
function encryptMessage($key, $message) {
    $iv = random_bytes(openssl_cipher_iv_length(ENCRYPTION_METHOD));  // Generate a random IV
    $encrypted = openssl_encrypt($message, ENCRYPTION_METHOD, $key, 0, $iv);  // Encrypt the message
    return base64_encode($iv . $encrypted);  // Combine IV and encrypted message, then base64 encode
}
```

### Message Decryption

To decrypt messages, the IV is extracted, and the same key is used for decryption.

```php
function decryptMessage($key, $encryptedMessage) {
    $data = base64_decode($encryptedMessage);
    $ivLength = openssl_cipher_iv_length(ENCRYPTION_METHOD);
    $iv = substr($data, 0, $ivLength);
    $encrypted = substr($data, $ivLength);
    return openssl_decrypt($encrypted, ENCRYPTION_METHOD, $key, 0, $iv);  // Decrypt the message
}
```

## End-to-End Flow

1. **Starting a Chat**  
   - A user generates a unique session key using the "Generate Key" button.  
   - This key is shared manually with others to join the session.

2. **Joining a Chat**  
   - Users enter the session key to join the chat.  
   - The server validates the key by checking for the corresponding JSON file.

3. **Sending a Message**  
   - The client sends the plaintext message to the server along with the session key.  
   - The server encrypts the message using AES-256-CBC and stores it in the JSON file.

4. **Receiving Messages**  
   - The client fetches encrypted messages from the server.  
   - The client decrypts the messages using the session key and displays them.

5. **Deleting a Chat**  
   - Users can delete the chat, which removes the corresponding JSON file from the server.

6. **Automatic Cleanup**  
   - A cron job deletes all JSON files every minute to ensure no data persists on the server longer than necessary.

![Secure Chat Logo](img/1)
![Secure Chat Logo](img/2)
![Secure Chat Logo](img/3)




## Server-Side File Storage

Chat data is temporarily stored in a `chat_sessions` directory. Each session is saved as a JSON file named after the session key. 

### Example JSON File Structure

```json
{
    "messages": [
        {
            "message": "encrypted_message",
            "timestamp": "2025-01-02 12:34:56"
        },
        {
            "message": "another_encrypted_message",
            "timestamp": "2025-01-02 12:35:00"
        }
    ]
}
```
![Secure Chat Logo](img/4)

## Auto Cleanup (Cron Job)

A cron job ensures privacy by automatically deleting all `.json` files in the `chat_sessions` directory every minute.

### Cron Job Example

```bash
* * * * * php /path/to/delete_json_files.php
```

## Client-Side (Browser)

The client-side application is built with HTML/JavaScript and styled with Tailwind CSS. It includes features such as:  

- Generating a new key for chat sessions.  
- Joining existing chat sessions using a session key.  
- Sending and receiving encrypted messages.  
- Deleting chat sessions.  

Messages are fetched from the server every 2 seconds using JavaScript's `setInterval()`.

## Security Considerations

- **AES-256-CBC**: A secure symmetric encryption algorithm requiring a secret key for both encryption and decryption.  
- **Random IV**: A new IV is used for each encryption, ensuring two identical messages won't encrypt to the same ciphertext.  
- **Key Management**: The session key is generated at the start of each chat session and must remain secret. If compromised, the messages can be decrypted.  

## Future Improvements

1. **Key Sharing**  
   - Secure key exchange mechanisms to avoid manual sharing and improve security.

2. **Enhanced User Experience**  
   - Features like real-time notifications or improved session management.

## Conclusion

This chat application offers a simple, highly secure messaging system that ensures privacy through end-to-end encryption. With AES-256-CBC encryption and automatic data deletion, your messages remain private, and no sensitive data is stored on the server.  

