# AddWise - User Authentication System

A secure and modern user authentication system built with PHP, featuring Google OAuth integration, email verification, and password management.

## Features

-  Secure User Authentication
-  Google OAuth Integration
-  Email Verification System
-  Password Reset Functionality
-  Responsive Design
-  Session Management
-  Security Best Practices

## Project Structure

```
addwise/
├── .env                    # Environment variables (not tracked in git)
├── .gitignore             # Git ignore rules
├── auth.php               # Authentication helper functions
├── composer.json          # PHP dependencies
├── dashboard.php          # User dashboard
├── db.php                 # Database configuration
├── forgot-password.php    # Password recovery
├── google-callback.php    # Google OAuth callback handler
├── google-login.php       # Google OAuth login
├── login.php             # Traditional login
├── logout.php            # Session termination
├── reset-otp.php         # OTP verification
├── save-token.php        # Token management
├── signup.php            # User registration
└── verify.php            # Email verification
```

## Prerequisites

- PHP 7.4 or higher
- MySQL/MariaDB
- Composer
- XAMPP/WAMP/LAMP stack
- Google OAuth credentials

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/SUDHEER176/addwisetech.git
   cd addwise
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Create a `.env` file in the root directory with the following content:
   ```
   GOOGLE_CLIENT_ID=your_client_id_here
   GOOGLE_CLIENT_SECRET=your_client_secret_here
   GOOGLE_REDIRECT_URI=http://localhost/addwise/google-callback.php
   ```

4. Set up the database:
   - Create a new MySQL database named 'addwise'
   - Import the database schema (if provided)
   - Update database credentials in `db.php`

5. Configure your web server:
   - Point your web server to the project directory
   - Ensure PHP has write permissions for session handling

## Google OAuth Setup

1. Go to the [Google Cloud Console](https://console.cloud.google.com/)
2. Create a new project or select an existing one
3. Enable the Google+ API
4. Create OAuth 2.0 credentials
5. Add authorized redirect URIs
6. Copy the Client ID and Client Secret to your `.env` file


## Usage

1. **User Registration**
   - Visit `signup.php`
   - Fill in the registration form
   - Verify email through the sent link

2. **Login**
   - Traditional login: Use `login.php`
   - Google login: Use `google-login.php`

3. **Password Reset**
   - Visit `forgot-password.php`
   - Enter email address
   - Follow the reset instructions

4. **Dashboard**
   - Access user dashboard after successful login
   - View and manage account settings

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

 
Happy Task managing with Addwise
