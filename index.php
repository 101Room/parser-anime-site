<?php
set_time_limit(0);

libxml_use_internal_errors(true);

$start = microtime(true);

$url = 'http://animevost.org/zhanr/';
$refererUrl = 'http://animevost.org';
$nPagePause = 4;

$data = curlGetContents($url, $refererUrl);

if ($data['code'] == 200){

	$doc = new DOMDocument();
	$doc->loadHTML($data['data']);
	$xPath = new DOMXpath($doc);

	$startPage = 1;
	$endPage = parseNumberLastPage($xPath);

	echo 'Парсер начал работу...<br>';

	while($startPage <= $endPage){
		$link = "{$url}page/$startPage/";

		$data = curlGetContents($link, $refererUrl);

		if ($data['code'] == 200){
			$doc = new DOMDocument();
			$doc->loadHTML($data['data']);
			$xPath = new DOMXpath($doc);

			$data = [];

			// Название
			$d1 = parseContent($xPath, "//div[@id='dle-content']/div/div/h2/a");
			// Год выпуска
			$d2 = parseContent($xPath, "//div[@id='dle-content']/div[@class='shortstory']/div[@class='shortstoryContent']/table/tr/td/p", 'Год выхода:', 12);
			// Жанр
			$d3 = parseContent($xPath, "//div[@id='dle-content']/div[@class='shortstory']/div[@class='shortstoryContent']/table/tr/td/p", 'Жанр:', 6);
			// Тип
			$d4 = parseContent($xPath, "//div[@id='dle-content']/div[@class='shortstory']/div[@class='shortstoryContent']/table/tr/td/p", 'Тип:', 5);
			// Количество серий
			$d5 = parseContent($xPath, "//div[@id='dle-content']/div[@class='shortstory']/div[@class='shortstoryContent']/table/tr/td/p", 'Количество серий:', 18);

			// Режиссёр
			$d6 = parseGetProducer($xPath);

			// Описание
			$d7 = parseContent($xPath, "//div[@id='dle-content']/div[@class='shortstory']/div[@class='shortstoryContent']/table/tr/td/p", 'Описание:', 10);

			// Массив для записи в файл
			$c = count($d1);
			for($i=0;$i<$c;$i++){
				$data[] = implode('|', [$d1[$i], $d2[$i], $d3[$i], $d4[$i], $d5[$i], $d6[$i], $d7[$i]]);
			}

			unset($d1, $d2, $d3, $d4, $d5, $d6, $d7, $c);

			file_put_contents('data/' . str_replace(['http:', 'https:', '//', '/', '.'], ['', '', '', '-', '_'], rtrim($link, '/')) . '.txt', implode("\n", $data));

			// Каждую n-страницу делаем паузу в 3 сек.
			if ($startPage % $nPagePause == 0){
				sleep(3);
			}

		} else {
			file_put_contents('data/errors.txt', date('d-m-Y H:i:s', time()) . ' Страница недоступна: ' . $data['errors'][0][1] . "\n", FILE_APPEND);
		}

		$startPage++;
	}

	echo 'Парсер завершил работу за ' . round(microtime(true) - $start, 1) . ' сек.<br>';

} else {
	die('Что-то пошло не так');
}

/** Функции */

/**
 * Прочесть содержимое файла в строку при помощи cUrl
 *
 * @param      $pageUrl Ссылка-источник
 * @param      $baseUrl Ссылка referer
 * @param int  $pauseTime Пауза между запросами
 * @param bool $retry Разрешить / не разрешить повторение
 * @return mixed
 */
function curlGetContents($pageUrl, $baseUrl, $pauseTime = 4, $retry = true) {
	$errors = [];

	$ch = curl_init();

	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_USERAGENT, getRandomUserAgent());

	curl_setopt($ch, CURLOPT_URL, $pageUrl);
	curl_setopt($ch, CURLOPT_REFERER, $baseUrl);

	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

	$response['data'] = curl_exec($ch);

	$ci = curl_getinfo($ch);

	if($ci['http_code'] != 200 && $ci['http_code'] != 404) {
		$errors[] = [1, $pageUrl, $ci['http_code']];

		if($retry) {
			sleep($pauseTime);
			$response['data'] = curl_exec($ch);
			$ci = curl_getinfo($ch);

			if($ci['http_code'] != 200 && $ci['http_code'] != 404){
				$errors[] = [2, $pageUrl, $ci['http_code']];
			}
		}
	}

	$response['code'] = $ci['http_code'];
	$response['errors'] = $errors;

	curl_close($ch);

	return $response;
}

/**
 * Получить случайный заголовок браузера
 *
 * @return mixed
 */
function getRandomUserAgent()
{
	$userAgents = [
		'Mozilla/5.0 (Windows NT 6.3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/66.0.3359.181 Safari/537.36',
		'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:40.0) Gecko/20100101 Firefox/40.1',
		'Opera/9.80 (X11; Linux i686; Ubuntu/14.10) Presto/2.12.388 Version/12.16',
		'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_3) AppleWebKit/537.75.14 (KHTML, like Gecko) Version/7.0.3 Safari/7046A194A',
		'Mozilla/5.0 (compatible; YandexBot/3.0; +http://yandex.com/bots)',
		'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
		'Mozilla/5.0 (Windows; U; Win 9x 4.90; SG; rv:1.9.2.4) Gecko/20101104 Netscape/9.1.0285',
		'Lynx/2.8.8dev.3 libwww-FM/2.14 SSL-MM/1.4.1',
		'Mozilla/5.0 (Windows NT 6.1; WOW64; Trident/7.0; AS; rv:11.0) like Gecko',
	];

	$random = mt_rand(0, count($userAgents)-1);

	return $userAgents[$random];
}

/**
 * Номер последней страницы
 *
 * @param DOMXpath $xPath
 * @return mixed
 */
function parseNumberLastPage(DOMXpath $xPath){
	$q = $xPath->query("//div[@id='dle-content']/div/table/*/td[@class='block_4']/a[last()]");
	return $q->item(0)->textContent;
}

/**
 * Получить режиссера
 *
 * @param DOMXpath $xPath
 * @return array
 */
function parseGetProducer(DOMXpath $xPath){
	$q00 = $xPath->query("//div[@id='dle-content']/div[@class='shortstory']/div[@class='shortstoryContent']/table/tr/td/p[5]/strong");
	foreach ($q00 as $item) {
		$q11[] = ($item->textContent == 'Режиссёр: ');
	}

	$q1 = $xPath->query("//div[@id='dle-content']/div[@class='shortstory']/div[@class='shortstoryContent']/table/tr/td/p[5]");
	foreach ($q1 as $item) {
		$q2[] = mb_substr($item->textContent, 10);
	}

	foreach ($q11 as $k => $item) {
		$result[] = empty($item) ? '' : $q2[$k];
	}
	return $result;
}

/**
 * Получить контент
 *
 * @param DOMXpath $xPath
 * @param string   $query Запрос xPath
 * @param string   $compare Строка для поиска
 * @param int      $lenCut Длина обрезаемого слова
 * @return array
 */
function parseContent(DOMXpath $xPath, $query = '//', $compare = '', $lenCut = 0)
{
	$result = [];
	$i = 0;

	$q = $xPath->query($query);
	if (empty($compare)){
		foreach ($q as $k => $item) {
			$result[] = mb_substr($item->textContent, $lenCut);
		}

	} else {
		foreach ($q as $k => $item) {
			if (strpos($item->textContent, $compare) !== false){
				$result[$i++] = mb_substr(str_replace("\n", ' ', $item->textContent), $lenCut);
			}
		}
	}
	return $result;
}

/**
 * Отображение данных для отладки
 *
 * @param $data
 */
function varDump($data)
{
	echo '<pre>';
	print_r($data);
	echo '</pre>';
}