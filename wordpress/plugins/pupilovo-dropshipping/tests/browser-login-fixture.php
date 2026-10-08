<?php
if (PHP_SAPI !== 'cli') exit(1);
require '/var/www/html/wp-load.php';
require_once ABSPATH.'wp-admin/includes/user.php';
$path=__DIR__.'/browser-fixture.local.json';
if (($argv[1]??'')==='cleanup') {
 if(file_exists($path)){$d=json_decode(file_get_contents($path),true);if(!empty($d['id']))wp_delete_user((int)$d['id']);unlink($path);}
 global $wpdb;
 $table=\Pupilovo\SupplierHub\Infrastructure\Database\Schema::table('suppliers');
 $ids=$wpdb->get_col("SELECT id FROM {$table} WHERE name LIKE 'Browser QA psh-browser-%'");
 foreach($ids as $id){$wpdb->delete(\Pupilovo\SupplierHub\Infrastructure\Database\Schema::table('supplier_sources'),['supplier_id'=>(int)$id],['%d']);$wpdb->delete($table,['id'=>(int)$id],['%d']);}
 echo 'Cleaned '.count($ids).' demo supplier profiles'.PHP_EOL;
 echo "Fixture removed\n";exit;
}
if(file_exists($path)) {echo "Fixture already exists\n";exit(1);}
$name='psh-browser-'.bin2hex(random_bytes(5));
$pass=wp_generate_password(30,true,true);
$id=wp_insert_user(['user_login'=>$name,'user_email'=>$name.'@example.test','user_pass'=>$pass,'role'=>'administrator']);
if(is_wp_error($id))throw new RuntimeException($id->get_error_message());
file_put_contents($path,wp_json_encode(['id'=>$id,'user'=>$name,'pass'=>$pass]));
chmod($path,0600);
echo "Temporary browser test account created\n";
