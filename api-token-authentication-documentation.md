# API Token Authentication for Third-Party Applications

This document describes the new API endpoint for obtaining authentication tokens for third-party applications.

## Endpoint

```
POST /api/token
```

## Description

This endpoint allows third-party applications to authenticate users and obtain a token that can be used for subsequent API requests. The endpoint accepts email and password credentials and returns a token along with basic user information.

## Request

### Headers

```
Content-Type: application/json
Accept: application/json
```

### Body

```json
{
  "email": "user@example.com",
  "password": "user_password"
}
```

## Response

### Success (200 OK)

```json
{
  "token": "1|abcdefghijklmnopqrstuvwxyz123456789",
  "user": {
    "id": 1,
    "name": "User Name",
    "email": "user@example.com"
  },
  "role": "admin"
}
```

### Error (401 Unauthorized)

```json
{
  "message": "Invalid credentials",
  "errors": {
    "email": ["These credentials do not match our records."]
  }
}
```

## Using the Token

After obtaining a token, include it in the `Authorization` header of subsequent API requests:

```
Authorization: Bearer 1|abcdefghijklmnopqrstuvwxyz123456789
```

## Example Usage

### cURL

```bash
curl -X POST \
  http://your-domain.com/api/token \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -d '{
    "email": "user@example.com",
    "password": "user_password"
  }'
```

### PHP

```php
$ch = curl_init('http://your-domain.com/api/token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'email' => 'user@example.com',
    'password' => 'user_password'
]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$data = json_decode($response, true);
$token = $data['token'];

// Use token in subsequent requests
$ch = curl_init('http://your-domain.com/api/protected-endpoint');
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);
// ...
```

### JavaScript (Fetch API)

```javascript
// Get token
fetch('http://your-domain.com/api/token', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  },
  body: JSON.stringify({
    email: 'user@example.com',
    password: 'user_password'
  })
})
.then(response => response.json())
.then(data => {
  const token = data.token;
  
  // Use token in subsequent requests
  fetch('http://your-domain.com/api/protected-endpoint', {
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json'
    }
  })
  .then(response => response.json())
  .then(data => {
    // Handle response
  });
});
```

## Logout Endpoint

```
POST /api/logout-token
```

### Description

This endpoint allows third-party applications to invalidate an authentication token when a user logs out. The token used in the request will be revoked and can no longer be used for API requests.

### Request

#### Headers

```
Authorization: Bearer {token}
Accept: application/json
```

### Response

#### Success (200 OK)

```json
{
  "message": "Token revoked successfully"
}
```

#### Error (401 Unauthorized)

```json
{
  "message": "Unauthenticated"
}
```

### Example Usage

#### cURL

```bash
curl -X POST \
  http://your-domain.com/api/logout-token \
  -H 'Authorization: Bearer 1|abcdefghijklmnopqrstuvwxyz123456789' \
  -H 'Accept: application/json'
```

#### PHP

```php
$ch = curl_init('http://your-domain.com/api/logout-token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Accept: application/json'
]);

$response = curl_exec($ch);
$data = json_decode($response, true);
// Handle response
```

#### JavaScript (Fetch API)

```javascript
fetch('http://your-domain.com/api/logout-token', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${token}`,
    'Accept': 'application/json'
  }
})
.then(response => response.json())
.then(data => {
  // Handle response
  console.log(data.message);
});
```

## Security Considerations

- These endpoints should only be used over HTTPS to ensure credentials are encrypted during transmission.
- Tokens should be stored securely in your application and never exposed to users.
- Consider implementing token expiration and refresh mechanisms for long-lived applications.
- Always invalidate tokens when users log out to prevent unauthorized access.
