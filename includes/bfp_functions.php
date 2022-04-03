<?php
$debug = false;

/*
 * Добавляем новое меню в Админ Консоль 
 */
 
// Хук событие 'admin_menu', запуск функции 'mfp_Add_My_Admin_Link()'
add_action( 'admin_menu', 'bfp_Add_My_Admin_Link' );
 

 
// Добавляем новую ссылку в меню Админ Консоли
function bfp_Add_My_Admin_Link()
{
 add_menu_page(
 'BRP brutforce protect', // Название страниц (Title)
 'BRP brutforce protect', // Текст ссылки в меню
 'manage_options', // Требование к возможности видеть ссылку
 'brutforce-protect/includes/bfp-first-acp-page.php' // 'slug' - файл отобразится по нажатию на ссылку
 );
}


function bfp_set_variables(){
	$a = array (
		'salt' => 'salt_for_checkings',
		'psw_cookie_live' => 60*30, //время работы без подтверждения пароля  30 минут
		'psw_delay' => 100,			//60*5;//задержка при брутфорсе в секундах - 5  минут
		'psw_limit' => 3, 			//лимит неудачных попыток ввода пароля
		//$is_login = test_sesion_login(0); //Состояние логина если параметр true - запускается в отладочном режиме
		//$debug = true; //флаг отладки
	);
	
	add_option( 'bfp_options', $a );  //сохранить в базе WP
	
	
}

function bfp_drop_variables(){
	delete_option( 'bfp_options' );
}




function drop_wp_login_hash_table ($link) {
	$drop_sql = "DROP TABLE `wp_login_hash`";
	mysqli_multi_query($link,$drop_sql); //удалить таблицу
	
}

function istall_wp_login_hash_table($link) {

	$sql = "
		SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';
		SET AUTOCOMMIT = 0;
		START TRANSACTION;
		SET time_zone = '+00:00';

		-- DROP TABLE IF EXISTS `wp_login_hash`;
		CREATE TABLE `wp_login_hash` (
		  `id` int(11) NOT NULL,
		  `ip` bigint(22) NOT NULL,
		  `ip_txt` varchar(32) NOT NULL,
		  `hash` varchar(32) NOT NULL,
		  `login` varchar(32) NOT NULL,
		  `time` datetime NOT NULL,
		  `fails` int(11) NOT NULL
		) ENGINE=InnoDB DEFAULT CHARSET=utf8;

		--
		-- Индексы сохранённых таблиц
		--

		--
		-- Индексы таблицы `wp_login_hash`
		--
		ALTER TABLE `wp_login_hash`
		  ADD PRIMARY KEY (`id`),
		  ADD KEY `hash_ip` (`ip`),
		  ADD KEY `kr_hash` (`hash`),
		  ADD KEY `kr_login` (`login`),
		  ADD KEY `kr_us_hsh_time` (`time`);

		--
		-- AUTO_INCREMENT для сохранённых таблиц
		--

		--
		-- AUTO_INCREMENT для таблицы `wp_login_hash`
		--
		ALTER TABLE `wp_login_hash`
		  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
		COMMIT;
	
	";
	
	mysqli_multi_query($link,$sql); //Создать таблицу
		
}



//начало основного функционала-------------------------------------


function bfp_Brutforce_checking($lg,$IP_adr,$psw) {	
	global $alt_link, $debug; //, $debug;

	//$login = preg_replace('~\D+~','',$lg); 
	
	$first = substr($lg, 0, 1); 
	$a_num = array(1,2,3,4,5,6,7,8,9,'+');
	
	$login = $lg;
	if (in_array($first, $a_num) ) {     //если логин это телефон - приготовить его
		if ($debug) echo 'Первый символ указывает что это талефон ' . $login .'<br>';
	
		$ph = preg_replace('/[^0-9]/', '', $lg); //выкидываем все лишние символы из телефона
		if ($ph[0] == '8') {
			$login = '7'. substr($ph,1);
		}		
		else $login = $ph;
	}
	
	$a = get_option('bfp_options');
	
	$psw_limit = $a['psw_limit'];
	$psw_delay = $a['psw_delay'];	
	$link = $alt_link;
	$ip = $IP_adr;
	
	
	if ($debug) echo 'подготовленный логин ' . $login .'<br>';
	
	
	if (  is_user_logged_in() ) {  //если залогинен
		if($debug) echo 'Пользователь залогинен!!!! Разлогиниваемся!... <br>';
		wp_logout();
	}else {							 //если НЕ залогинен
		if ($debug) echo 'Логина НЕТ - надо проверять: нет ли брутфорса?... <br>';
	}	
	 
		if (!$login == '') {
		//елси  не залогинен - то перед логином - проверка есть ли такая пара IP-логин в базе	
			$sql_ip ="SELECT * FROM `wp_login_hash` WHERE ( (`ip`=inet_aton('".$IP_adr."'))  AND (`login`='".$login."') )   ";
			if ($debug) echo $sql_ip . "<br>";
			
			$selected_f = $link->query($sql_ip);
			$sql_len = $selected_f -> num_rows;
			if ($debug) echo 'таких строк нашлось - ' . $sql_len . "<br>";
		
		
			if (($sql_len==0) AND (!$login == '') ) {
				if ($debug) echo 'пара ip-login ip - ' . $IP_adr. ' login - '.$login . ' НЕ нашлась в базе - добавляем.... <br>';
				//ip  в базе не нашлось - добавляем ip и время
				$sql_insert = "INSERT INTO `wp_login_hash`(`ip`, `ip_txt`, `login`, `time`, `fails`) VALUES (INET_ATON('".$IP_adr."'),'" .$IP_adr. "','".$login."',now(),0)";
				if ($debug) echo ($sql_insert . '<br>');		
				$link->query($sql_insert);
				$last_id = mysqli_insert_id($link);
				
				if ($debug) echo 'втсавилась запись с id - ' . $last_id . '<br>';
				
				// теперь проверить валидность пароля если ок то войти с отметкой об успехе или неуспехе входа 
				return bfp_try_login ($login,$psw, $last_id, 0, $psw_limit); 
				
				// в логи - зарегистрирован новый IP !!
				//add_log('login', 1, 0,'login.php - зарегистрирован новый IP  ' .$_SERVER['REMOTE_ADDR']);
				
				//check_psw(htmlspecialchars($login),0,0);//делаем проверку пароля для нового IP  строку из формы защищаем.
				
			} 
			else { //ip  в базе есть такая пара IP-логин проверяем передоз по времени и числу попыток
				if ($debug) echo 'пара ip-login ip - ' . $IP_adr. ' login - '.$login . ' НАШЛСЬ в базе - проверяем.... <br>';
			
				while ( $sel_f = $selected_f->fetch_assoc() ) {
				
				$clock = strtotime($sel_f['time']); //получили время посл обращения  в формате UNIX
				$fails_com = $sel_f['fails']; //получили число попыток в сроке
				$id = $sel_f['id'];//получили id записи
				
				$failsIP = bfp_is_brutforse_ip ($ip,$psw_delay, $psw_limit, $link);
										
				// здесь проврека брутфорса по IP  и логину
				$fails = 0;
				if (!$failsIP) {
					$failsLogin = bfp_is_brutforse_login ($login, $psw_delay, $psw_limit, $link); // брутфорс логин проверяем только если не нашли брут по IP
					if ($failsLogin) {
						$fails = $failsLogin;
						if($debug) echo 'есть брутфорс по LOGIN - ' . $fails . '<br>';
					}
				} else {
					$fails = $failsIP;
					if($debug) echo 'есть брутфорс по IP - ' . $fails . '<br>';
				}
				
				
				//$fails = is_brutforse_login ($login); // это для проверки - потом стереть!!!
				
				//если ое проверки не сработали  $fails остается текущим (небольшим но ненулевым)
				
				
				if($debug) echo 'количество брутфорс попыток - ' . $fails . '<br>';
				
				$tm = time();
				
				$wait = $tm - $clock; //получили время с последней авторизации в сек

				if($debug) echo 'время сейчас - ' .  date('Y-m-d H:i:s', $tm) . ' - ' .$tm. ' время из базы - ' . $sel_f['time'] . ' - ' . $clock . ' разница в сек - ' .$wait .'<br>';
				
			//echo "время с посленей авторизации (мин) - " . $wait/60 . " сравниваем с " . $psw_delay/60 . "<br>";

				
				if ($fails > $psw_limit) {
						//если проверка на число попыток не пройдена
					if($debug) echo 'число попыток - ' . $fails . ' больше лимита - ' . $psw_limit . '<br>';	
					if($debug) echo 'время с последней попытки - ' . $wait . ' ожидание в случае ощибки - ' . $psw_delay . '<br>';	
				
						if ( ($wait > $psw_delay) AND ($login == $sel_f['login']) ){ 
							if($debug) echo 'Ожидание окончено проверяем  пароль еще раз...<br>';	
							//echo "время подождали -  пропускаем еще разок <br>";
							//обнуляем неудачные попытки
							return bfp_try_login ($login,$psw,$id,$fails_com,$psw_limit);
							
						} else {
							if($debug) echo 'Есть брутфорс  - отвергаем проверку <br>';	
							//add_log('login', 0, 0,'login.php - попытка брутфорса с IP ' .$_SERVER['REMOTE_ADDR']);	
							
							/*
							$err_msg = '<strong>Обнаружена попытка взлома подбором пароля. В проверке пароля отказано!</strong>'. 
							'<br>Число неудачных входов с IP ' . $IP_adr . ' - ' . $failsIP . 
							'<br>Число неудачных входов с Логина ' . $login . ' - ' . $failsLogin . 
							'<br>Время с последней попытки - ' . $wait . ' осталось подождать - ' . $psw_delay-$wait . '<br>';
							
							*/
							$wt = $psw_delay-$wait;
							$lgm = '';
							if ($failsLogin>0) $lgm = '<br>Число неудачных входов c Login - ' . $failsLogin; 
							
							$err_msg = '<strong>Обнаружена попытка взлома подбором пароля. В проверке пароля отказано!</strong>'. 
							'<br>Число неудачных входов c IP - ' . $failsIP .
							$lgm .
							'<br>Время с последней попытки - ' . $wait . 
							'<br>Осталось подождать - ' . $wt;		
							
							//remove_all_actions('wp_login'); //прекратить вход!
							return	new WP_Error('brutforse_detected',$err_msg);
							if($debug) echo $err_msg;
												
						}
						
					} elseif ($login == $sel_f['login']) {
						if($debug) echo 'брутфорса пока нет - разрешенное число попытко не превышено, проверяем пароль..... <br>';
						return bfp_try_login ($login,$psw,$id,$fails_com,$psw_limit);
						
				//check_psw($_POST['password'],$sel_f['fails'],$sel_f['id']);//делаем проверку пароля
					}
				
				
				} //while
			}
		} else {
			if($debug) echo 'Передан пустой логин - в проверке отказано!<br>';
		}
		
}

function bfp_try_login ($login,$psw, $id, $fails, $psw_limit) {
	global $debug, $alt_link;
	if($debug) echo 'переданное число fails - ' . $fails . '<br>';
	$fls = $fails+1;
	$link = $alt_link;
	
	
	
	$user = get_user_by( 'login', $login );
		

	if ( ! $user ) {
		$sql_upd = "UPDATE `wp_login_hash` SET `time`='".date('Y-m-d H:i:s', time())."',`fails`='".$fls."' WHERE `id`='".$id."'";
		if($debug) echo $sql_upd . '<br> Yеправильный логин!!!! <br>';					
		$link->query($sql_upd);	
		
		return new WP_Error(
			'invalid_username or password',
			__( 'Unknown username or password. Check again.' )
		);
	}

	if ( ! wp_check_password( $psw, $user->user_pass, $user->ID ) ) {
		if($debug) echo ' пароль не совпал - увеличиваем счетчик неудачных попыток и обновляем время <br>';	
						
			$sql_upd = "UPDATE `wp_login_hash` SET `time`='".date('Y-m-d H:i:s', time())."',`fails`='".$fls."' WHERE `id`='".$id."'";
			if($debug) echo $sql_upd . '<br>';					
			$link->query($sql_upd);	
			
			$trs = $psw_limit - $fails;	
			return new WP_Error('incorrect_password','<strong>Пароль не верен.</strong><br>Число возможных попыток до блокировки: ' . $trs); 
			
		
		} else {
			if($debug) echo ' пароль ПРАВИЛЬНЫЙ! - обнуляем счетчик неудачных попыток и обновляем время <br>';	
					
			$sql_upd = "UPDATE `wp_login_hash` SET `time`='".date('Y-m-d H:i:s', time())."',`fails`='0' WHERE `id`='".$id."'";
			if($debug) echo $sql_upd . '<br>';					
			$link->query($sql_upd);
			/* такая грубая попытка вызывает зацикливание
			$creds = array();
			$creds['user_login'] = $login;
			$creds['user_password'] = $psw;
			$creds['remember'] = true;

			$user_ = wp_signon( $creds, false );

			if  ( ( is_wp_error($user_) ) AND ($debug) ) {
			   echo $user_->get_error_message();
			}
			*/
			return $user;
		}
		
}	



function bfp_is_brutforse_ip ($ip,$psw_delay, $psw_limit, $link) {
	//проверить не слишком ли много неудачных попытко входа с этого IP в течении времени
	global $debug;// $psw_delay, $psw_limit, $link;
		//проверка есть ли такой IP в базе	
		
	if ($debug) echo 'проверка на брутфорс по IP ' .$ip . '<br>';
	$ipL = ip2long($ip);
	
	//$time_limit = time() - $psw_delay;
	$time_limit = date('Y-m-d H:i:s',time() - $psw_delay);
	
	/*
	$t = time();
	$time_limit = date('Y-m-d H:i:s',$t-$psw_delay);
	
	if ($debug) echo ' время брута ' . $time_limit . "<br>";
	if ($debug) echo ' время СЕЙЧАС ' . date('Y-m-d H:i:s', $t) . "<br>";
	
	*/
	//достать все записи за последние 5 минут в которых с этого IP были неудачные попытки входа	
	$sql_ip ="SELECT * FROM `wp_login_hash` WHERE ( (`ip`='".$ipL."') AND (`fails`>0) AND (`time` > '$time_limit')  )";
	
	if ($debug) echo $sql_ip . "<br>";
	
	$selected_f = $link->query($sql_ip);
	$sql_len = $selected_f -> num_rows;
	
	$fls =0;
	if ($sql_len >0 ) {
		while ( $sel_f = $selected_f->fetch_assoc() ) {
			$fls += $sel_f['fails'];
			if ($debug) echo $sel_f['time'] . 'int ' . strtotime($sel_f['time']) . "<br>";				
		}
		
		if($fls<$psw_limit) {		
			if ($debug) echo "брутфорса по IP не обнаружено - " . $ip . "<br>";	
			return false; //выход - брутфорса нет
		}
		else {//брутфорс по IP есть подсчитать число неудачных попыток

			
			if ($debug) echo "обнаружен брутфорс c IP - " . $ip . " число неудачных попытко входа - " .$fls. " <br>";	
			return $fls; //выход - брутфорс ЕСТЬ		
		}
	} else {
		if ($debug) echo "брутфорса по IP не обнаружено - " . $ip . "<br>";	
			return false; //выход - брутфорса нет		
	}
	
}


function bfp_is_brutforse_login ($login, $psw_delay, $psw_limit, $link) {
	//проверить не слишком ли много неудачных попыток входа с этого логина в течении времени
	global $debug;
 
	if ($debug) echo 'проверка на брутфорс по Логин <br>';
	$t = time();
	$time_limit = date('Y-m-d H:i:s',$t-$psw_delay);
	
	if ($debug) echo ' время брута ' . $time_limit . "<br>";
	if ($debug) echo ' время СЕЙЧАС ' . date('Y-m-d H:i:s', $t) . "<br>";
	
	//достать все записи за последние 5 минут в которых с этого логина были неудачные попытки входа	
	$sql_login ="SELECT * FROM `wp_login_hash` WHERE ( (`login`='".$login."') AND (`fails`>0) AND (`time`>'".$time_limit."')  )";
	
	
	if ($debug) echo $sql_login . "<br>";
	
	$selected_f = $link->query($sql_login);
	$sql_len = $selected_f -> num_rows;
	
	$fls =0;
	if ($sql_len >0 ) {	
		while ( $sel_f = $selected_f->fetch_assoc() ) {
			$fls += $sel_f['fails'];	
			if ($debug) echo $sel_f['time'] . 'int ' . strtotime($sel_f['time']) . "<br>";			
		}
		
		if($fls<$psw_limit) {		
			if ($debug) echo "брутфорса по Логин не обнаружено - " . $login . "<br>";	
			return false; //выход - брутфорса нет
		}
		else {//брутфорс по IP есть подсчитать число неудачных попыток

			
			if ($debug) echo "обнаружен брутфорс c Логина - " . $login . " число неудачных попытко входа - " .$fls. " <br>";	
			return $fls; //выход - брутфорс ЕСТЬ		
		}
	} else {
		if ($debug) echo "брутфорса по Логин не обнаружено - " . $login . "<br>";	
			return false; //выход - брутфорса нет		
	}
	
}




?>