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

$searchRegion = ['Брестского', 'Жабинковского'];

// Функция для отправки сообщения Вконтакте
function vkMessage ($id, $message) {
	$url = 'https://api.vk.com/method/messages.send';
	$params = array(
        'user_id' => $id,    // Кому отправляем
        'message' => $message,   // Что отправляем
        'access_token' => 'YOU TOKEN HERE',  // access_token можно вбить хардкодом, если работа будет идти из под одного юзера
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

if(date('N') <= 5) {
	// Парсинг нужной нам страницы
	$ch = curl_init('http://www.pravo.by/ofitsialnoe-opublikovanie/novye-postupleniya/');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; MSIE 7.0; Windows NT 5.2)');
		curl_setopt($ch, CURLOPT_REFERER, 'http://www.pravo.by');
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
		    "NAME", // Имя отправителя
		    "YOU MAIL HERE" // Почта отправителя
		);
		// Кому письмо (через запятую указываем более одного ящика)
		$to = 'MAIL HERE';

	// Вытаскиваем нужную информацию
	foreach($dom->find('div.usercontent dl') as $dl) {
		$text = trim($dl->plaintext);
		preg_replace('/\s\s+/', ' ', $text);
		foreach ($dl->find('a') as $link) {
			$linkSend = "www.pravo.by" .$link->attr['href']; // Ссылка на документ
			$messageTempVk = $text. "<br>" .$linkSend. "<br>";
			$messageVk = trim(str_replace(chr(194).chr(160), ' ', html_entity_decode($messageTempVk))); // Текст для письма Вконтакте
			$messageTempTel = preg_replace('/\s+/', ' ', $text). "\n" .$linkSend. "\n";
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
				vkMessage('ID VK', $wordSearch);
				$mailMess[] = "<tr><td>" .$wordSearch. "</td></tr>"; // Записываем данные в массив для отправки на почту
			}
	}

	// Поиск законов для конкретных регионов
	foreach ($message as $region) {
		switch($region[0]){ // Условия поиска по первому символу
			case "9":
			foreach ($searchRegion as $city) {
				if (strpos($region, $city)) {
	        		$mailRegion[] = "<tr><td>" .$region. "</td></tr>";
	        		vkMessage('ID VK', $region);
	        	}
	        	else {
	        		false;
	        	}
	        }
    	}	
	}

	// Отправляем одно письмо на электронную почту (кому, тема сообщения, текст, куда)
	$mailSMTP->send($to, 'Законы от ' .date('d.m.Y'), '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN"><h1>Список опубликованных законов по фильтру:</h1><table>' .implode('', $mailMess).'<h2>Региональные законы:</h2>' .implode('', $mailRegion). '</table><h3>' .$pravo. '</h3>', $from);

	// Цикл по массиву с нужными данными для Telegram
	foreach ($messageT as $word) {
		switch($word[0]){ // Условия поиска по первому символу
			case "1":
			case "2":
			case "3":
			case "5":
				// Отправляем сообщение в telegram
				file_get_contents('https://api.telegram.org/TOKENHERE/sendMessage?chat_id=YOUCHATIDHERE&disable_web_page_preview=false&parse_mode=html&text='.urlencode($word));
			}
	}

	file_get_contents('https://api.telegram.org/TOKENHERE/sendMessage?chat_id=YOUCHATIDHERE&disable_web_page_preview=false&parse_mode=html&text=<b>Региональные законы:</b>');

	// Поиск законов для конкретных регионов для Telegram
	foreach ($messageT as $regionT) {
		switch($regionT[0]){ // Условия поиска по первому символу
			case "9":
			foreach ($searchRegion as $city) {
				if (strpos($regionT, $city)) {
	        		file_get_contents('https://api.telegram.org/TOKENHERE/sendMessage?chat_id=YOUCHATIDHERE&disable_web_page_preview=false&parse_mode=html&text='.urlencode($regionT));
	        	}
	        	else {
	        		false;
	        	}
	        }
    	}
	}

	file_get_contents('https://api.telegram.org/TOKENHERE/sendMessage?chat_id=YOUCHATIDHERE&disable_web_page_preview=false&parse_mode=html&text='.urlencode($pravo));
}
else {
	false;
}
?>