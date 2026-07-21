<?php

use Illuminate\Support\Facades\Route;

// Authentication routes have been moved to routes/api.php
// This file is kept for backward compatibility but all auth routes
// are now defined in api.php to maintain a single source of truth
// for the SPA API setup.

// All authentication routes are available in routes/api.php:
// - POST /api/register
// - POST /api/login
// - POST /api/logout
// - POST /api/forgot-password
// - POST /api/reset-password
// - GET /api/verify-email/{id}/{hash}
// - POST /api/email/verification-notification
