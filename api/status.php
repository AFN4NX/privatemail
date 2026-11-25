<?php
declare(strict_types=1);
require_once __DIR__.'/../bootstrap.php';Auth::require();session_write_close();
try{
    // Successful response body is two integer fields, normally 35–60 bytes.
    Security::json(MultiMailbox::status());
}catch(Throwable $e){Security::json(['error'=>'Mailbox status unavailable.'],503);}
