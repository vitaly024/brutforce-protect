<?php
/*
Plugin Name: Brute force protect (BFP)
Description: Обеспечивает защиту от брутфорса по логину и паролю.
Version: 1.1.7
Author: Виталий Головков
License: GPLv2 or later
*/

/* Updates:
1.1.7 - ошибка при инсталяции - исправлен $alt_link	03.04.2022
В версии 1.1.6 уточнено сообщение о бруте "Пароль не верен. Число возможных попыток до блокировки: 1 " 
В версии 1.1.5 исправлена ошибка: невозможно войти если с этого компа уже был вход. (добавлен принудительный выход перед проверками + проверка на пустой логин передвинута в самое начало )
В версии 1.1.4 исправлена ошибка с входом нового пользователя (не зареганного в таблице брутфорс-атак)
В версии 1.1.3 добавлены функции сохранениея options и анализа лидеров брутфорса по IP & Login  - bfp-first-acp-page.php
*/

// Подключаем mfp-functions.php, используя require_once, чтобы остановить скрипт, если mfp-functions.php не найден
require_once plugin_dir_path(__FILE__) . 'includes/bfp_functions.php';
require_once( ABSPATH . "wp-includes/pluggable.php" ); //подключаем только теперь после определения нашй

//подключаем хуки для процедур активации-дактивации плагина

register_activation_hook(__FILE__ , 'bfp_activate' );
//register_deactivation_hook(__FILE__ , 'bfp_deactivate' );
register_uninstall_hook(__FILE__ , 'bfp_uninstall');

if (!isset($alt_link2) ){ //если не установлено в файле wp-config
	$alt_link2 = new mysqli(DB_HOST,DB_USER,DB_PASSWORD,DB_NAME); 
	$alt_link2->query('set names utf8');
	$alt_link2->query("set lc_time_names='ru_RU'");
}

//после логина переброс на главную страницу
add_filter('login_redirect', 'btf_login_redirect');

 
function btf_login_redirect() {
   return '/';
}


//подключаем основной функционал - проверка на брут перед логином
add_filter( 'authenticate', 'bfp_check_brutforse', 300, 3 ); //выполнить самой последней.



function bfp_check_brutforse( $user, $username, $password ){
	//проверку на брутфорс вставить СЮДА!!!!!!!!
	
	$user = bfp_Brutforce_checking($username,$_SERVER['REMOTE_ADDR'],$password);
	 return $user;	
}



//Функция выполняемая при активации плагина
function bfp_activate() {	
	$alt_link3 = new mysqli(DB_HOST,DB_USER,DB_PASSWORD,DB_NAME); 
	istall_wp_login_hash_table($alt_link3);	//создать таблицу
	bfp_set_variables();					//инициализировать переменные	
}


//Функция выполняемая при удалении плагина
function bfp_uninstall() {
	$alt_link3 = new mysqli(DB_HOST,DB_USER,DB_PASSWORD,DB_NAME); 
	drop_wp_login_hash_table($alt_link3); 	//грохнуть таблицу
	bfp_drop_variables();					//грохнуть переменные
}





?>