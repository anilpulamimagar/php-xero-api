<?php
define('XERO_CLIENT_ID',     'E5600F01940F4ECAB9AFB7F83CC0D5D9');
define('XERO_CLIENT_SECRET', 't7sCe9zgCGcMLSsr6MflL1wuXtgmgFSfa6EClH_tVMdWsF6M');
define('XERO_REDIRECT_URI',  'http://localhost:8000/callback.php');

define('XERO_AUTH_URL',  'https://login.xero.com/identity/connect/authorize');
define('XERO_TOKEN_URL', 'https://identity.xero.com/connect/token');

// Scopes for sign-in + invoices
define('XERO_SCOPES', 'openid profile email accounting.invoices accounting.contacts offline_access');
