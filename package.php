<?php
function main($source_path , $make_path , $package_name, $key_path ){
	$make_path = trim($make_path);
	#ディレクトリパスチェック
	if(!preg_match("/\/$/", $make_path){
		$make_path = $make_path."/";
	}

	$name = $package_name;
	$crx = $name.".crx";
	$pub = $name.".pub";
	$sig = $name.".sig";
	$zip = $name.".zip";

	$crx_path = $make_path.$crx;
	$pub_path = $make_path.$pub;
	$sig_path = $make_path.$sig;
	$zip_path = $make_path.$zip;

	#zip作成
	if(!filezip($source_path , $zip_path)){
		throw new Exception("can't make zip file");
		exit;
	}


	#秘密鍵読み込み
	$key_b = fopen($key_path , "rb");
	$priv_key = fread($key_b , 8192);
	fclose($key_b);

	#ZIP読み込み
	$zip_i = fopen($zip_path, "rb");
	$zip_b = fread($zip_i , 8192);
	fclose($zip_i);

	$pkeyid = openssl_get_privatekey($priv_key);
	$sig_data = mk_sig($zip_b,$pkeyid);
	$pub_data = mk_pub($pkeyid);

	$pub_open = fopen($pub_path,"wb");
	fwrite($pub_open , $pub_data);

	$crmagic_hex="4372 3234"; # Cr24
	$version_hex="0200 0000"; # 2


	$pub_len_hex = byte_swap(sprintf('%08x\n',strlen($pub_data)));
	$sig_len_hex = byte_swap(sprintf('%08x\n',strlen($sig_data)));

	mk_crx( $crx_path , $crmagic_hex , $version_hex , $pub_len_hex , $sig_len_hex , $pub_data , $sig_data , $zip_b);
	unlink($pub_path);
	unlink($zip_path);
}
function mk_crx($crx_path ,$crmagic_hex ,$version_hex,$pub_len_hex ,$sig_len_hex , $pub_data  , $sig_data , $zip_b){
	$crx_open = fopen($crx_path , "wb");
	if($crx_open){
		if(flock($crx_open, LOCK_EX)){
			$crc_data = binary($crmagic_hex.$version_hex.$pub_len_hex.$sig_len_hex).$pub_data.$sig_data.$zip_b;
			if(!fwrite($crx_open, $crc_data)){
				throw new Exception("Error can't write crx");
			}
		}

	}
}

function mk_sig($zip_b,$pkeyid){
	openssl_sign($zip_b, $signature, $pkeyid);
	return $signature;
}

function mk_pub($pkeyid){

	$key_list = openssl_pkey_get_details($pkeyid);
	$pubkey_pem = $key_list["key"];
	#DER
	$pubkey_der = pem2der($pubkey_pem);
	return $pubkey_der;
}
function pem2der($pem_data) {
   $begin = "BEGIN PUBLIC KEY-----";
   $end   = "-----END";
   $pem_data = substr($pem_data, strpos($pem_data, $begin)+strlen($begin));
   $pem_data = substr($pem_data, 0, strpos($pem_data, $end));
   $der = base64_decode($pem_data);

   return $der;
}
function byte_swap($byte){
	return substr($byte,6,2).substr($byte, 4,2).substr($byte, 2,2).substr($byte, 0,2);
}
function binary($nubers){
	$nuber = preg_replace("/ /" , "" , $nubers);
	$binary = "";
	$binary = pack("H*",$nuber);

	return $binary;
}

function filezip($sourcepath , $zip_path){
	$filelists = filelist($sourcepath);
	$zip = new ZipArchive();
	$res = $zip->open($zip_path , ZipArchive::CREATE );

	if ($res === true){
		$pwd_path = getcwd();
		chdir($sourcepath);
		foreach ($filelists as $file) {
			//echo $file;
			$zip->addFile($file);
		}
		$zip->close();
		chdir($pwd_path);
	}else{
		return Flase;
	}
}
function filelist($dir_path){
	if ($dir = opendir($dir_path)) {
		$file_list = array();
	    while (($file = readdir($dir)) !== false) {
	        if ($file != "." && $file != "..") {
	            array_push($file_list , $file);
	        }
	    }
    	closedir($dir);
    	return $file_list;
	}
}