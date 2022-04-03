<div class="wrap">
<h1>BrutForce Protect (BFP)</h1>
 <?php
    if ( isset($_POST['Submit']) )
    {  
       if ( function_exists('current_user_can') &&
            !current_user_can('manage_options') )
                die ( _e('Hacker?', 'ljusers') );

        if (function_exists ('check_admin_referer') )
        {
            check_admin_referer('bfp_form');
        }
		
		
		$a = array (
		'psw_delay' => $_POST['psw_delay'],			//60*5;//задержка при брутфорсе в секундах - 5  минут
		'psw_limit' => $_POST['psw_limit'], 			//лимит неудачных попыток ввода пароля
		);
	
		update_option( 'bfp_options', $a );  //сохранить в базе WP
		
		echo '<h3 style="color: green;">Данные обновлены! </h3>';
	}
	$a = get_option('bfp_options');
		$psw_delay = $a['psw_delay']; 			//60*5;//задержка при брутфорсе в секундах - 5  минут
		$psw_limit = $a['psw_limit'];  			//лимит неудачных попыток ввода пароля
?>

<p> Настройки фильтрации:
  <form id="form1" name="form1" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>?page=brutforce-protect/includes/bfp-first-acp-page.php">
  <p> <input name="psw_delay" type="text" value="<?php echo $psw_delay ?>" /> 
  Время ожидания при брутфорсе в секундах</p>
  <p><input name="psw_limit" type="text" value="<?php echo $psw_limit ?>" /> 
  Разрешенное число попыток</p>
	<?php
		if (function_exists ('wp_nonce_field') ) {   //поставим защитное поле     
            wp_nonce_field('bfp_form');
        }
	?>	
	<input type="hidden" name="action" value="update" />
    <input type="hidden" name="page_options" value="bfp_options" />
	
  <p>
    <input type="submit" name="Submit" value="Submit" />
  </p>
</form>

<h2> Пятерка лидеров брутфорса по Логину</h2>
<table width="800" border="1">
  <tr>
    <td>#</td>
    <td>Дата последней попытки</td>
    <td>Логин</td>
    <td>IP</td>
    <td>Попытки</td>
  </tr>
	<tr>
<?php
	$sql_login  = "SELECT * FROM `wp_login_hash` ORDER BY `wp_login_hash`.`fails` DESC LIMIT 5";
	//if ($debug) echo $sql_ip . "<br>";		
	$selected_f = $alt_link->query($sql_login);
		$i=1;
	while ( $sel_f = $selected_f->fetch_assoc() ) {
		echo '<tr><td>'.$i. '</td><td>'.$sel_f['time'].'</td><td>'.$sel_f['login'].'</td><td>'.$sel_f['ip_txt'].'</td><td>'.$sel_f['fails'].'</td></tr>';				
		$i++;
	}
?>
</table>
<h2> Пятерка лидеров брутфорса по IP</h2>
<table width="800" border="1">
  <tr>
    <td>#</td>
    <td>IP</td>
    <td>Попытки</td>
  </tr>
  <tr>
  <?php
	$sql_ip = 'SELECT `ip_txt`, SUM(`fails`) AS ip_fls FROM `wp_login_hash` GROUP BY `ip` ORDER BY ip_fls DESC LIMIT 5';
		$selected_f = $alt_link->query($sql_ip);
		$i=1;
	while ( $sel_f = $selected_f->fetch_assoc() ) {
		echo '<tr><td>'.$i. '</td><td>'.$sel_f['ip_txt'].'</td><td>'.$sel_f['ip_fls'].'</td></tr>';				
		$i++;
	}
	?>
</table>

</div>