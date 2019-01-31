<?php

// Подключаем используемые библиотеки
include '/storage/ssd1/810/1194810/public_html/simple_html_dom.php'; // http://simplehtmldom.sourceforge.net/ version 1.5
include '/storage/ssd1/810/1194810/public_html/SendMailSmtpClass.php'; // https://github.com/Ipatov/SendMailSmtpClass version 1.1

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
        'access_token' => 'YOU-TOKEN_HERE',  // access_token можно вбить хардкодом, если работа будет идти из под одного юзера
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

// Функция для отправки сообщения Viber
function viberMessage ($message){
	$curl = curl_init();

	curl_setopt_array($curl, array(
	  CURLOPT_URL => 'https://chatapi.viber.com/pa/send_message',
	  CURLOPT_RETURNTRANSFER => true,
	  CURLOPT_ENCODING => '',
	  CURLOPT_MAXREDIRS => 10,
	  CURLOPT_TIMEOUT => 30,
	  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	  CURLOPT_USERAGENT => 'Mozilla/1.22 (compatible; MSIE 2.0; Windows 95)',
	  CURLOPT_CUSTOMREQUEST => 'POST',
	  CURLOPT_POSTFIELDS => JSON_encode($message),
	  CURLOPT_HTTPHEADER => array(
		'Cache-Control: no-cache',
		'Content-Type: application/JSON',
		'X-Viber-Auth-Token: YOU-TOKEN_HERE'
	  ),
	));
 
	$response = curl_exec($curl);
	$err = curl_error($curl);
 
	curl_close($curl);
 
	if ($err) {
	  echo 'cURL Error #:' . $err;
	} else {
	  echo $response;
	}
}

if(date('N') <= 5) {
	// Парсинг нужной нам страницы
	$ch = curl_init('http://www.pravo.by/ofitsialnoe-opublikovanie/novye-postupleniya/');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, true);
		curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/1.22 (compatible; MSIE 2.0; Windows 95)');
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

	// Вытаскиваем нужную информацию
	foreach($dom->find('div.usercontent dl') as $dl) {
		$text = trim($dl->plaintext);
		preg_replace('/\s\s+/', ' ', $text);
		foreach ($dl->find('a') as $link) {
			$linkSend = 'www.pravo.by' .$link->attr['href']; // Ссылка на документ
			$messageTempVk = $text. '<br>' .$linkSend. '<br>';
			$messageVk = trim(str_replace(chr(194).chr(160), ' ', html_entity_decode($messageTempVk))); // Текст для письма Вконтакте
			$messageTempTel = preg_replace('/\s+/', ' ', $text). "\n" .$linkSend. "\n";
			$messageTelegram = trim(str_replace(chr(194).chr(160), ' ', html_entity_decode($messageTempTel))); // Текст для письма в telegram
			$message[] = $messageVk; // Массив с нужными данными
			$messageT[] = $messageTelegram; // Массив с нужными данными для telegram
		}
	}

	function allLaws($array) {
		// Цикл по массиву с нужными данными
		foreach ($array as $laws) {
			switch($laws[0]){ // Условия поиска по первому символу
				case '1':
				case '2': 
				case '3': 
				case '5': 
					// Отправляем сообщение Вконтакте
					vkMessage('ID-VK', $laws);
					global $mailLawsAll;
					$mailLawsAll[] = '<tr><td>' .$laws. '</td></tr>'; // Записываем данные в массив для отправки на почту
				}
		}
	}

	function regionalLaws($array, $searchRegion) {
		// Поиск законов для конкретных регионов
		foreach ($array as $region) {
			switch($region[0]){ // Условия поиска по первому символу
				case '9':
				foreach ($searchRegion as $city) {
					if (strpos($region, $city)) {
						global $mailLawsRegion;
		        		$mailLawsRegion[] = '<tr><td>' .$region. '</td></tr>';
		        		vkMessage('ID-VK', $region);
		        	}
		        	else {
		        		false;
		        	}
		        }
	    	}	
		}
	}

	// Функция для поиска и отправки всех законов для telegram и viber
	function allLawsTV($array) {
		// Цикл по массиву с нужными данными для Telegram и Viber
		foreach ($array as $laws) {
			switch($laws[0]){ // Условия поиска по первому символу
				case '1':
				case '2':
				case '3':
				case '5':
					// Отправляем сообщение в telegram
					file_get_contents('https://api.telegram.org/YOU-TOKEN-HERE/sendMessage?chat_id=YOU-CHAT-ID_HERE&disable_web_page_preview=false&parse_mode=html&text='.urlencode($laws));
					// Отправляем сообщение в viber
					$messageViberAll['receiver'] = 'ID-VIBER';
					$messageViberAll['sender.name'] = 'SENDER-NAME';
					$messageViberAll['type'] = 'text';
					$messageViberAll['text'] = $laws;
					viberMessage($messageViberAll);
				}
		}
	}

	// Функция для поиска и отправки региональных законов для telegram и viber
	function regionalLawsTV($array, $searchRegion, $pravo) {
		file_get_contents('https://api.telegram.org/YOU-TOKEN-HERE/sendMessage?chat_id=YOU-CHAT-ID_HERE&disable_web_page_preview=false&parse_mode=html&text=<b>Региональные законы:</b>');

		// Поиск законов для конкретных регионов для Telegram
		foreach ($array as $region) {
			switch($region[0]){ // Условия поиска по первому символу
				case '9':
				foreach ($searchRegion as $city) {
					if (strpos($region, $city)) {
		        		file_get_contents('https://api.telegram.org/YOU-TOKEN-HERE/sendMessage?chat_id=YOU-CHAT-ID_HERE&disable_web_page_preview=false&parse_mode=html&text='.urlencode($region));
		        		// Отправляем сообщение в viber
						$messageViberReg['receiver'] = 'ID-VIBER'; // Уникальный ID
						$messageViberReg['sender.name'] = 'SENDER-NAME';
						$messageViberReg['type'] = 'text';
						$messageViberReg['text'] = $region;
						viberMessage($messageViberReg);
		        	}
		        	else {
		        		false;
		        	}
		        }
	    	}
		}
		// Сообщение об общем количестве опубликованных законов
		file_get_contents('https://api.telegram.org/YOU-TOKEN-HERE/sendMessage?chat_id=YOU-CHAT-ID_HERE&disable_web_page_preview=false&parse_mode=html&text='.urlencode($pravo));
	}

	allLaws($message);
	regionalLaws($message, $searchRegion);

	// Данные для smtp ('логин', 'пароль', 'хост', 'порт', 'кодировка письма')
	$mailSMTP = new SendMailSmtpClass('MAIL-HERE', 'PASSWORD-HERE', 'ssl://smtp.gmail.com', 465, 'UTF-8');
	// От кого письмо
	$from = array(
	    'NAME-HERE', // Имя отправителя
	    'MAIL-HERE' // Почта отправителя
	);
	// Кому письмо (через запятую указываем более одного ящика)
	$to = 'MAIL-HERE';
	// Отправляем одно письмо на электронную почту (кому, тема сообщения, текст, куда)
	$mailSMTP->send($to, 'Законы от ' .date('d.m.Y'), '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN"><table><tr><td><h2>Список опубликованных законов по фильтру:</h2></td></tr>' .implode('', $mailLawsAll).'<tr><td><h2>Региональные законы:</h2></td></tr>' .implode('', $mailLawsRegion). '<tr><td><h3>' .$pravo. '</h3></td></tr></table>', $from);

	allLawsTV($messageT);
	regionalLawsTV($messageT, $searchRegion, $pravo);
	
}
else {
	false;
}