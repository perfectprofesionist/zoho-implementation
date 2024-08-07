# ZohoController

This PHP controller manages the integration with Zoho and Erply, handling authentication and data synchronization processes.

## Table of Contents

- [Introduction](#introduction)
- [Features](#features)
- [Installation](#installation)
- [Usage](#usage)
- [Endpoints](#endpoints)
- [Environment Variables](#environment-variables)
- [License](#license)

## Introduction

The `ZohoController` is part of a Laravel application and is responsible for:
- Handling OAuth2 authentication with Zoho
- Synchronizing data between Zoho and Erply
- Logging sync requests

## Features

- **Zoho OAuth2 Authentication**: Handles the OAuth2 callback to authenticate users with Zoho.
- **Erply Synchronization**: Triggers synchronization of various endpoints with Erply.
- **Logging**: Logs synchronization requests for monitoring and debugging.

## Usage

Include the `ZohoController` in your routes or call its methods as needed in your application.

### Example

To trigger Erply endpoints synchronization, you can call:
```php
$zohoController = new ZohoController();
$zohoController->triggerErplyEndpoints();
```

## Endpoints

- **`triggerErplyEndpoints`**: Simulates data and logs synchronization requests to Erply.
- **`authCallBack`**: Handles the OAuth2 callback from Zoho, exchanging the authorization code for an access token and saving the authenticated user.

## Environment Variables

Ensure the following environment variables are set in your `.env` file:

- `zoho_url`: Base URL for Zoho API.
- `grant_type`: Grant type for OAuth2.
- `client_id`: Zoho client ID.
- `client_secret`: Zoho client secret.
- `redirect_uri`: URI to redirect to after Zoho authentication.

Example `.env` entries:
```
zoho_url=https://accounts.zoho.com
grant_type=authorization_code
client_id=YOUR_CLIENT_ID
client_secret=YOUR_CLIENT_SECRET
redirect_uri=https://yourdomain.com/zoho/auth/callback
```
