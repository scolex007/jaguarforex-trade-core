# JaguarForex Trading Platform

A comprehensive Forex trading platform with MLM capabilities built on CodeIgniter 4.

## Features

- User management with secure authentication
- WordPress integration with SSO
- Two-factor authentication
- Forex broker integration
- MLM referral system
- Wallet and commission management
- Payment processing

## Technology Stack

- PHP 7.4+ with CodeIgniter 4
- MySQL database
- JWT authentication
- Google Authenticator integration

## Setup Instructions

1. Clone the repository
2. Configure your database in `app/Config/Database.php`
3. Run database migrations
4. Configure environment variables
5. Start the application

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Composer

## License

Proprietary

Project Overview: JaguarForex Trading Platform

  Your local folder contains a web application called "myJaguar" built on the CodeIgniter 4 PHP framework. This is a
  multi-level marketing (MLM) trading platform focused on forex trading with broker integration.

  Technology Stack

  - Backend: PHP with CodeIgniter 4 framework
  - Database: MySQL (inferred from configuration)
  - Authentication: Custom authentication with JWT token support and WordPress integration
  - Security: Includes two-factor authentication via Google Authenticator

  Core Features

  1. User Management
    - Registration and login system
    - Email verification
    - Password recovery
    - Two-factor authentication
    - User profile management
  2. Trading Integration
    - Integration with forex brokers (including Exness and Roboforex)
    - Account linking capabilities
    - Support for both demo and live trading accounts
  3. MLM Structure
    - Binary marketing structure (2x matrix)
    - Referral management system
    - Commission tracking
    - Sponsor-based registration
  4. Financial System
    - Wallet management
    - Transaction history
    - Multiple payment methods support
    - Commission payouts
  5. WordPress Integration
    - SSO (Single Sign-On) with WordPress
    - Cross-domain cookie authentication
    - User data synchronization

  Administration

  - Admin dashboard for system oversight
  - User management tools
  - Commission configuration
  - System settings management

  Security Features

  - Email verification
  - Google Authenticator integration
  - Password validation with complexity requirements
  - Honeypot protection against bots
  - Session management

  The system appears to be a comprehensive forex trading platform with MLM capabilities, allowing users to register, link their
   trading accounts, refer others to the platform, and earn commissions through a binary matrix structure. It also includes
  integration with WordPress, likely for content management and front-end display.
