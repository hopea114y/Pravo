<meta charset="UTF-8">
<?php

// Подключаем используемые библиотеки
include 'simple_html_dom.php'; // http://simplehtmldom.sourceforge.net/ version 1.5
include 'SendMailSmtpClass.php'; // https://github.com/Ipatov/SendMailSmtpClass version 1.1

// Массив Headers в http запрос
$headers = [
    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
	'Accept-language: ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
	'Content-Type: text/plain',
	'AlexaToolbar-ALX_NS_PH: AlexaToolbar/alx-4.0.3'
];

// Функция для отправки сообщения Вконтакте
function vkMessage ($id, $message) {
	$url = 'https://api.vk.com/method/messages.send';
	$params = array(
        'user_id' => $id,    // Кому отправляем
        'message' => $message,   // Что отправляем
        'access_token' => '41f5ae4e97031d5dff83368e915ea0d5fc5aeb5478642e1682f693e8405ab871dde247f09fdd784a5d803',  // access_token можно вбить хардкодом, если работа будет идти из под одного юзера
        'random_id' => mt_rand(20, 99999999),
        'v' => '5.85'
    );

    // В $result вернется id отправленного сообщения
    $result = file_get_contents($url, false, stream_context_create(array(
        'http' => array(
            'method'  => 'POST',
            'header'  => 'Content-type: application/x-www-form-urlencoded',
            'content' => http_build_query($params)
        )
    )));
}

// Парсинг нужной нам страницы
$ch = curl_init('http://www.pravo.by/ofitsialnoe-opublikovanie/novye-postupleniya/');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HEADER, true);
	curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; MSIE 7.0; Windows NT 5.2)');
	curl_setopt($ch, CURLOPT_REFERER, 'https://www.yandex.by/');
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_TIMEOUT, 30);
	curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
	$result = curl_exec($ch);
	curl_close($ch);

  	$dom = new simple_html_dom();

  	$dom->load($result);

  	$all = $dom->find('.h-link__count b', 1);

  	$pravo = '<b>Всего ' .$all->plaintext. ' актов опубликовано ' .date('d.m.Y'). '</b>'; // Общее количество опубликованных актов

  	// Данные для smtp ('логин', 'пароль', 'хост', 'порт', 'кодировка письма')
  	$mailSMTP = new SendMailSmtpClass('YOU MAIL HERE', 'PASSWORD HERE', 'ssl://smtp.gmail.com', 465, "UTF-8");
	// От кого письмо
	$from = array(
	    "Grinevitskiy", // Имя отправителя
	    "grinevitskiyaleksandr@gmail.com" // Почта отправителя
	);
	// Кому письмо (через запятую указываем более одного ящика)
	$to = 'you email here';

// Вытаскиваем нужную информацию
foreach($dom->find('div.usercontent dl') as $dl) {
	$text = trim($dl->plaintext);
	preg_replace('/\s\s+/', ' ', $text);
	foreach ($dl->find('a') as $link) {
		$linkSend = "www.pravo.by" .$link->attr['href']; // Ссылка на документ
		$messageTempVk = $text. "<br>" .$linkSend. "<br>";
		$messageVk = trim(str_replace(chr(194).chr(160), ' ', html_entity_decode($messageTempVk))); // Текст для письма Вконтакте
		$messageTempTel = $text. "\n" .$linkSend. "\n";
		$messageTelegram = trim(str_replace(chr(194).chr(160), ' ', html_entity_decode($messageTempTel))); // Текст для письма в telegram
		$message[] = $messageVk; // Массив с нужными данными
		$messageT[] = $messageTelegram; // Массив с нужными данными для telegram
	};
}

// Цикл по массиву с нужными данными
foreach ($message as $wordSearch) {
	switch($wordSearch[0]){ // Условия поиска по первому символу
		case "1":
		case "2": 
		case "3": 
		case "5": 
			// Отправляем сообщение Вконтакте
			// id101487135
			vkMessage('you id vk', $wordSearch);
			$mailMess[] = "<tr><td>" .$wordSearch. "</td></tr>"; // Записываем данные в массив для отправки на почту
		}
}

// Отправляем одно письмо на электронную почту (кому, тема сообщения, текст, куда)
$mailSMTP->send($to, 'Законы от ' .date('d.m.Y'), '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN"><table>' .implode('', $mailMess). '</table>' .$pravo, $from);

// Цикл по массиву с нужными данными для telegram
foreach ($messageT as $word) {
	switch($word[0]){ // Условия поиска по первому символу
		case "1":
		case "2":
		case "3":
		case "5":
			// Отправляем сообщение в telegram
			file_get_contents('https://api.telegram.org/TOKENHERE/sendMessage?chat_id=YOUCHATIDHERE&disable_web_page_preview=false&parse_mode=html&text='. urlencode($word));
		}
}
?>
